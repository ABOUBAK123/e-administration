<?php

namespace App\Services;

/** Serveur OnlyOffice à utiliser pour une ressource donnée. */
final class OnlyOfficeServer
{
    public function __construct(
        public readonly string $url,
        public readonly string $secret,
        public readonly bool $disableCert = false,
        /** true : serveur propre à l'administration ; false : serveur global. */
        public readonly bool $dedicated = false,
    ) {
    }
}
