<?php

namespace App\DTO;

class GoogleSigninResponse
{
    public string $token;
    public UserData $user;
}

class UserData
{
    public string $id;
    public string $email;
    public string $name;
    public string $avatarUrl;
}
