<?php

namespace App\Http\Controllers\Auth;

use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Wallet;
use App\EmailVerification;
use Mail;
use App\Mail\UserEmailVerification;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'dob' => 'required|date',
            'country' => 'required|string|max:50',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:25|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(array $data)
    {

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'dob' => $data['dob'],
            'country' => $data['country'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'phone' => $data['phone'],
            'role_id' => 2
        ]);

        EmailVerification::create([
            'user_id' => $user->id,
            'token' => str_random(40)
        ]);
 
        Mail::to($user)->queue(new UserEmailVerification($user));
        
        // create new wallet for the user.
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => env('TOKEN_API_URL'),
            // You can set any number of default request options.
            'timeout'  => 10.0
        ]);

        $response = $client->request('GET', 'account/create', [
            'http_errors' => false,
            'headers' => [
                "Authorization" => "API-KEY TESTKEY"
            ]
        ]);

        if ($response->getStatusCode() == 200) {
            $result = json_decode($response->getBody()->getContents());
            if ($result->success) {
                $user->wallet()->create([
                    'address' => $result->address,
                    'private_key' => $result->privateKey
                ]);
            }
        }

        return $user;
    }

    public function verifyEmail($token) {
        $verification = EmailVerification::where('token', $token)->first();
        if(isset($verification)){
            $user = $verification->user;
            if(!$user->email_verified) {
                $user->email_verified = 1;
                $user->save();
                $status = "Your email is verified. You can now login.";
            }else{
                $status = "Your email is already verified. You can now login.";
            }
        }else{
            return redirect('/login')->with('warning', "Sorry your email cannot be identified.");
        }
 
        return redirect('/login')->with('status', $status);
    }

    protected function registered(Request $request, $user)
    {
        $this->guard()->logout();
        return redirect('/login')->with('status', 'We sent you an activation code. Check your email and click on the link to verify.');
    }
}
