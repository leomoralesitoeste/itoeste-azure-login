<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
  public function redirectToOauthProvider()
  {
    return Socialite::driver('azure-oauth')->redirect();
  }

  public function handleOauthResponse()
  {
    $user = Socialite::driver('azure-oauth')->user();
    $azure_identi = $user->id;

    $authUser = $this->findOrCreateUser($user);

    //auth()->login($authUser, true);
    $code = $authUser ? md5(uniqid($authUser, true)) : 0;
    if ($authUser) {
      $authUser->azure_code = $code;
      if ($authUser->azure_identi == null) {
        $authUser->azure_identi = $azure_identi;
      }

      if ($authUser->id_tipo_usuario == 4) {
        $authUser->id_tipo_usuario = 3;
      }
      $authUser->save();
    }
    $url_mobile = config('app.mobile') . "?azure_code=" . $code;
    auth()->login($authUser, true);

    return redirect($url_mobile);


    return redirect(
      config('azure-oath.redirect_on_login')
    );
  }

  protected function findOrCreateUser($user)
  {
    $user_class = config('azure-oath.user_class');
    // $authUser = $user_class::where(config('azure-oath.user_id_field'), $user->id)->first();
    $user_local = $user_class::where('email', $user->email)->first();

    if ($user_local) {
      return $user_local;
    }
    // if ($authUser) {
    //     return $authUser;
    // }
    // else if($user_local){
    //     $user_local->azure_id = $user->id;
    //     $user_local->save();
    //     return $user_local;
    // }

    $UserFactory = new UserFactory();

    return $UserFactory->convertAzureUser($user);
  }
}
