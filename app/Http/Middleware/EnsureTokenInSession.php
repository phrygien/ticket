<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenInSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next): Response
    {
        $token = session('token');

        if (!$token) {
            return redirect()->route('login');
        }

        // Vérifie si le token a expiré
        if ($this->isTokenExpired($token)) {
            session()->forget('token'); // Optionnel : on peut aussi vider la session
            return redirect()->route('login');
        }

        return $next($request);
    }

    /**
     * Vérifie si le token JWT est expiré
     */
    protected function isTokenExpired(string $token): bool
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return true; // Token mal formé
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        if (!isset($payload['exp'])) {
            return true; // Pas d'expiration = non valide
        }

        return $payload['exp'] < time();
    }
}
