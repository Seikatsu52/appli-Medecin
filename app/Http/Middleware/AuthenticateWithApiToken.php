<?php

namespace App\Http\Middleware;

use App\Models\Authentification;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return new JsonResponse([
                'message' => 'Token d\'authentification manquant.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $authentification = Authentification::query()
            ->where('token', $plainToken)
            ->where('ipAppareil', $request->ip())
            ->with('patient')
            ->first();

        if (! $authentification || ! $authentification->patient) {
            return new JsonResponse([
                'message' => 'Token d\'authentification invalide pour cette adresse IP.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->setUserResolver(fn () => $authentification->patient);

        return $next($request);
    }
}
