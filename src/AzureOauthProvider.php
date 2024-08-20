<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\User;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Laravel\Socialite\Facades\Socialite;


class AzureOauthProvider extends AbstractProvider implements ProviderInterface
{
    const IDENTIFIER = 'AZURE_OAUTH';
    protected $scopes = ['User.Read.All', 'openid', 'profile', 'email'];
    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state)
    {
        // dd($this->buildAuthUrlFromBase('https://login.microsoftonline.com/common/oauth2/v2.0/authorize', $state));
        return $this->buildAuthUrlFromBase('https://login.microsoftonline.com/common/oauth2/v2.0/authorize', $state);
    }

    protected function getTokenUrl()
    {

        return 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    }

    protected function getTokenFields($code)
    {
      // dd($code);
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
            //'resource' => 'https://graph.microsoft.com',
        ]);
    }

    protected function getUserByToken($token, $email='')
    {
        // dd($token);

        $response = $this->getHttpClient()->get('https://graph.microsoft.com/beta/me/', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        $cuerpo = json_decode($response->getBody(), true);

        try {

          $response_manager = $this->getHttpClient()->get('https://graph.microsoft.com/v1.0/users/'.$cuerpo['id']."/manager", [
            'headers' => [
              'Authorization' => 'Bearer '.$token,
            ],
          ]);

          $cuerpo_manager = json_decode($response_manager->getBody(), true);

          //dd($cuerpo_manager);

      } catch (RequestException $e) {

          $cuerpo_manager = false;
      }

        if(!$cuerpo_manager){
          $cuerpo['data_manager'] = json_encode([]);
        }
        else{
          $cuerpo['data_manager'] = json_encode($cuerpo_manager);
        }

        // dd($cuerpo);
        //
        DB::table('callback_azure_user')->insert([
          'description'=>json_encode($cuerpo),
          'azure_user_id'=>$cuerpo['id'],
          'email'=>$email
        ]);

        $cuerpo['email'] = $email;
        return $cuerpo;
    }

    public function roles()
    {
        $tokens = explode('.', $this->user->idToken);

        return json_decode(static::urlsafeB64Decode($tokens[1]))->roles;
    }

    public static function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;

        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }


    public function user()
    {
        if ($this->hasInvalidState()) {
            return Socialite::driver('azure-oauth')->stateless()->user();
            // $user = Socialite::driver('azure-oauth')->user();

        }

        $response = $this->getAccessTokenResponse($this->getCode());

        $profile = json_decode( base64_decode( explode(".", $response['id_token'])[1]) );
        $email = $profile?$profile->email:'';
        $tokens = explode('.', $response['id_token']);

       // dd($profile);

        //return json_decode(static::urlsafeB64Decode($tokens[1]))->roles;
        $user = $this->mapUserToObject($this->getUserByToken(
            $token = Arr::get($response, 'access_token'),
            $email
        ));


        $user->idToken = Arr::get($response, 'id_token');
        $user->expiresAt = time() + Arr::get($response, 'expires_in');

        return $user->setToken($token)
                    ->setRefreshToken(Arr::get($response, 'refresh_token'));
    }

    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'                => $user['id'],
            'name'              => $user['displayName'],
            'email'             => $user['email'],

            'businessPhones'    => $user['businessPhones'],
            'displayName'       => $user['displayName'],
            'givenName'         => $user['givenName'],
            'jobTitle'          => $user['jobTitle'],
            'mail'              => $user['email'],
            'mobilePhone'       => $user['mobilePhone'],
            'officeLocation'    => $user['officeLocation'],
            'preferredLanguage' => $user['preferredLanguage'],
            'surname'           => $user['surname'],
            'userPrincipalName' => $user['userPrincipalName'],
            'manager_data'      => $user['data_manager']
        ]);
    }
}
