<?php

namespace App\Actions\Fortify;

use App\Helpers\Recaptcha;
use App\Models\User;
use App\Models\UserDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;
use Bouncer;

class CreateNewUser implements CreatesNewUsers
{

    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param array $input
     *
     * @return \App\Models\User
     */
    public function create(array $input)
    {
        Validator::make($input, [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms'    => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['required', 'accepted'] : '',
        ])->validate();

        // Recaptcha
        $recaptcha = (new Recaptcha())->check($input, 'register');

        if ( ! $recaptcha->ok()) {
            throw ValidationException::withMessages([
                'recaptcha' => 'Sigurnosna provjera nije uspjela. Pokušajte ponovno.',
            ]);
        }

        $public_user = User::create([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        Bouncer::assign('customer')->to($public_user);

        UserDetail::create([
            'user_id'    => $public_user->id,
            'fname'      => '',
            'lname'      => '',
            'address'    => '',
            'zip'        => '',
            'city'       => '',
            'state'      => '',
            'phone'      => '',
            'avatar'     => 'media/avatars/avatar1.jpg',
            'bio'        => '',
            'social'     => '',
            'role'       => 'customer',
            'status'     => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        return $public_user;
    }
}
