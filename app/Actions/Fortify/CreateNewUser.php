<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:20',
                'alpha_dash',
                Rule::unique(User::class)
            ],
            'role' => [
                'required',
                'string',
                'in:instrumentist,doctor,circulating'
            ],
            'phone' => [
                'nullable',
                'string',
                'max:8'
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'username' => $input['username'],
            'role' => $input['role'],
            'phone' => $input['phone'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
