<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthApiController extends BaseApiController
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/token",
     *     summary="Crear token de autenticación",
     *     description="Genera un nuevo token de acceso para la API",
     *     operationId="createToken",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","device_name"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@crecepyme.cl"),
     *             @OA\Property(property="password", type="string", format="password", example="password"),
     *             @OA\Property(property="device_name", type="string", example="API Client v1.0"),
     *             @OA\Property(property="abilities", type="array", @OA\Items(type="string"), example={"customers:read", "invoices:create"}),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2025-12-31T23:59:59Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Token creado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Token created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="access_token", type="string", example="1|abcdef123456..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time"),
     *                 @OA\Property(property="abilities", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email", type="string"),
     *                     @OA\Property(property="tenant_id", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciales inválidas",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Authentication failed"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function createToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:255',
            'abilities' => 'array',
            'abilities.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Default abilities if none provided
            $abilities = $request->get('abilities', ['*']);
            
            // Create Sanctum token
            $token = $user->createToken(
                $request->device_name,
                $abilities,
                $request->expires_at
            );

            // Log the token creation in our ApiToken model for tracking
            ApiToken::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $request->device_name,
                'token_hash' => hash('sha256', explode('|', $token->plainTextToken)[1]),
                'abilities' => $abilities,
                'expires_at' => $request->expires_at,
                'last_used_at' => null,
                'last_used_ip' => $request->ip(),
            ]);

            $this->logApiActivity('auth.create_token', $request, $user->id);

            return response()->json([
                'message' => 'Token created successfully',
                'data' => [
                    'access_token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $request->expires_at,
                    'abilities' => $abilities,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'tenant_id' => $user->tenant_id,
                    ]
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 401);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error creating authentication token');
        }
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_name' => 'required|string|max:255',
            'abilities' => 'array',
            'abilities.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Create new token
            $abilities = $request->get('abilities', ['*']);
            $token = $user->createToken(
                $request->device_name,
                $abilities,
                $request->expires_at
            );

            // Update our tracking
            ApiToken::where('user_id', $user->id)
                ->where('token_hash', hash('sha256', $request->bearerToken()))
                ->delete();

            ApiToken::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $request->device_name,
                'token_hash' => hash('sha256', explode('|', $token->plainTextToken)[1]),
                'abilities' => $abilities,
                'expires_at' => $request->expires_at,
                'last_used_at' => null,
                'last_used_ip' => $request->ip(),
            ]);

            $this->logApiActivity('auth.refresh_token', $request, $user->id);

            return response()->json([
                'message' => 'Token refreshed successfully',
                'data' => [
                    'access_token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $request->expires_at,
                    'abilities' => $abilities,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error refreshing authentication token');
        }
    }

    public function revokeToken(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Update our tracking
            if ($request->bearerToken()) {
                ApiToken::where('user_id', $user->id)
                    ->where('token_hash', hash('sha256', $request->bearerToken()))
                    ->delete();
            }

            $this->logApiActivity('auth.revoke_token', $request, $user->id);

            return response()->json([
                'message' => 'Token revoked successfully'
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error revoking authentication token');
        }
    }

    public function revokeAllTokens(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            // Revoke all user tokens
            $user->tokens()->delete();

            // Update our tracking
            ApiToken::where('user_id', $user->id)->delete();

            $this->logApiActivity('auth.revoke_all_tokens', $request, $user->id);

            return response()->json([
                'message' => 'All tokens revoked successfully'
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error revoking all authentication tokens');
        }
    }

    public function tokenInfo(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $token = $request->user()->currentAccessToken();
            
            if (!$user || !$token) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            $tokenData = [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                ]
            ];

            $this->logApiActivity('auth.token_info', $request, $user->id);

            return response()->json(['data' => $tokenData]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving token information');
        }
    }

    public function validateToken(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            return response()->json([
                'valid' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Token validation failed'
            ], 401);
        }
    }
}