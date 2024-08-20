<?php

namespace Metrogistics\AzureSocialite;
use App\Prefix;
use App\User;

class UserFactory
{
    protected $config;
    protected static $user_callback;

    public function __construct()
    {
        $this->config = config('azure-oath');
    }

    public function convertAzureUser($azure_user)
    {

        $manager_data = json_decode($azure_user->manager_data);
        $is_admin = $manager_data?true:false;


        $user_class = config('azure-oath.user_class');
        $user_map = config('azure-oath.user_map');
        $id_field = config('azure-oath.user_id_field');

        $new_user = new $user_class;
        // $new_user->$id_field = $azure_user->id;

        foreach($user_map as $azure_field => $user_field){
            $new_user->$user_field = $azure_user->$azure_field;
        }

        $callback = static::$user_callback;

        if($callback && is_callable($callback)){
            $callback($new_user);
        }

        $ex = explode('@', $new_user->email);
        $domain = array_pop($ex);
        //Prisma Medios de Pago S.A.
        $prefix = Prefix::where('dominio', $domain)->whereHas('empresa', function($q){
            $q->where('login_azure', '=', 1);
        })->first();

        if($prefix){
          if($is_admin){
            $user_manager = User::where('email', $manager_data->mail)->first();
            if(!$user_manager){
              $user_manager = User::create([
                'name'=> $manager_data->displayName,
                'email'=> $manager_data->mail,
                'id_tipo_usuario'=>3,
                'azure_identi'=>$manager_data->id,
                'id_empresa'=>$prefix->id_empresa
              ]);
            }
            $new_user->admin_id = $user_manager->id;
          }

          $new_user->id_empresa = $prefix->id_empresa;
          $new_user->id_tipo_usuario = 3;
          $new_user->azure_identi = $azure_user->id;
          $new_user->save();
        }
        else{
          $new_user = false;
        }

        return $new_user;
    }

    public static function userCallback($callback)
    {
        if(! is_callable($callback)){
            throw new \Exception("Must provide a callable.");
        }

        static::$user_callback = $callback;
    }
}
