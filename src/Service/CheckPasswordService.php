<?php

namespace App\Service;

class CheckPasswordService
{
    public function checkpsw(string $psw,string $psw_check): string {
        if ($psw !== $psw_check) {
            throw new \InvalidArgumentException('Les mots de passe ne correspondent pas.');
        }

        if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[^a-zA-Z0-9]).*$/', $psw)) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins une majuscule, un chiffre et un caractère spécial.');
        }

        return password_hash($psw , PASSWORD_BCRYPT);
    }
}