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
    public function handle(Request $request, Closure $next): Response
    {
        $token = session('token');
        $role = session('role');

        // Vérifie si le token existe
        if (!$token) {
            return redirect()->route('login')->with('error', 'Veuillez vous connecter.');
        }

        // Vérifie si le token a expiré
        if ($this->isTokenExpired($token)) {
            session()->flush();
            return redirect()->route('login')->with('error', 'Votre session a expiré.');
        }

        // Vérifie les permissions pour les routes protégées
        if ($this->isRestrictedRoute($request) && !$this->hasAccess($role)) {
            abort(403, 'Accès non autorisé. Vous devez être super administrateur.');
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

    /**
     * Vérifie si la route actuelle est restreinte
     */
    protected function isRestrictedRoute(Request $request): bool
    {
        $restrictedRoutes = [
            'users.list',
            'dashboard.sa-dashboard',
        ];

        return in_array($request->route()->getName(), $restrictedRoutes);
    }

    /**
     * Vérifie si l'utilisateur a accès (super admin uniquement)
     */
    protected function hasAccess(?string $role): bool
    {
        return $role === 'super_admin';
    }
}