<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\HandleApiErrors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class HandleApiErrorsTest extends TestCase
{
    use RefreshDatabase;

    protected HandleApiErrors $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new HandleApiErrors();
    }

    /** @test */
    public function it_formats_validation_errors_correctly()
    {
        $request = Request::create('/api/users', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $validationException = ValidationException::withMessages([
            'email' => ['The email field is required.'],
            'name' => ['The name field is required.'],
        ]);
        
        $response = $this->middleware->handle($request, function () use ($validationException) {
            throw $validationException;
        });
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('VALIDATION_ERROR', $data['error']['code']);
        $this->assertArrayHasKey('details', $data['error']);
        $this->assertArrayHasKey('email', $data['error']['details']);
        $this->assertArrayHasKey('name', $data['error']['details']);
    }

    /** @test */
    public function it_formats_authentication_errors()
    {
        $request = Request::create('/api/protected', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $response = $this->middleware->handle($request, function () {
            throw new AuthenticationException('Unauthenticated.');
        });
        
        $this->assertEquals(401, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('UNAUTHORIZED', $data['error']['code']);
        $this->assertEquals('No estás autenticado.', $data['error']['message']);
    }

    /** @test */
    public function it_formats_authorization_errors()
    {
        $request = Request::create('/api/admin', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $response = $this->middleware->handle($request, function () {
            throw new AuthorizationException('This action is unauthorized.');
        });
        
        $this->assertEquals(403, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('FORBIDDEN', $data['error']['code']);
    }

    /** @test */
    public function it_formats_model_not_found_errors()
    {
        $request = Request::create('/api/users/999', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $exception = new ModelNotFoundException();
        $exception->setModel(User::class, [999]);
        
        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });
        
        $this->assertEquals(404, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('NOT_FOUND', $data['error']['code']);
    }

    /** @test */
    public function it_includes_request_id_in_response()
    {
        $requestId = 'test-request-id-123';
        $request = Request::create('/api/test', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUEST_ID' => $requestId,
        ]);
        
        $response = $this->middleware->handle($request, function () {
            throw new \Exception('Test error');
        });
        
        $data = $response->getData(true);
        $this->assertEquals($requestId, $data['error']['request_id']);
    }

    /** @test */
    public function it_generates_request_id_if_not_provided()
    {
        $request = Request::create('/api/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $response = $this->middleware->handle($request, function () {
            throw new \Exception('Test error');
        });
        
        $data = $response->getData(true);
        $this->assertNotEmpty($data['error']['request_id']);
        $this->assertMatchesRegularExpression('/^[a-f0-9\-]+$/', $data['error']['request_id']);
    }

    /** @test */
    public function it_logs_exceptions_with_context()
    {
        Log::shouldReceive('channel')
            ->with('exceptions')
            ->once()
            ->andReturnSelf();
        
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'API Exception' &&
                       isset($context['error_id']) &&
                       isset($context['exception']) &&
                       isset($context['url']) &&
                       isset($context['method']);
            });
        
        $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $this->middleware->handle($request, function () {
            throw new \Exception('Test error');
        });
    }

    /** @test */
    public function it_includes_debug_info_in_local_environment()
    {
        app()->detectEnvironment(function () {
            return 'local';
        });
        
        $request = Request::create('/api/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $response = $this->middleware->handle($request, function () {
            throw new \Exception('Test error');
        });
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('debug', $data['error']);
        $this->assertArrayHasKey('exception', $data['error']['debug']);
        $this->assertArrayHasKey('file', $data['error']['debug']);
        $this->assertArrayHasKey('line', $data['error']['debug']);
        $this->assertArrayHasKey('trace', $data['error']['debug']);
    }

    /** @test */
    public function it_excludes_debug_info_in_production()
    {
        app()->detectEnvironment(function () {
            return 'production';
        });
        
        $request = Request::create('/api/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $response = $this->middleware->handle($request, function () {
            throw new \Exception('Test error');
        });
        
        $data = $response->getData(true);
        $this->assertArrayNotHasKey('debug', $data['error']);
    }

    /** @test */
    public function it_passes_through_successful_responses()
    {
        $request = Request::create('/api/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $expectedResponse = response()->json(['data' => 'success'], 200);
        
        $response = $this->middleware->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });
        
        $this->assertEquals($expectedResponse, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_formats_existing_error_responses()
    {
        $request = Request::create('/api/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $errorResponse = response()->json(['message' => 'Custom error'], 400);
        
        $response = $this->middleware->handle($request, function () use ($errorResponse) {
            return $errorResponse;
        });
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('BAD_REQUEST', $data['error']['code']);
        $this->assertEquals('Custom error', $data['error']['message']);
    }

    /** @test */
    public function it_includes_timestamp_in_error_response()
    {
        $request = Request::create('/api/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $response = $this->middleware->handle($request, function () {
            throw new \Exception('Test error');
        });
        
        $data = $response->getData(true);
        $this->assertArrayHasKey('timestamp', $data['error']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['error']['timestamp']);
    }

    /** @test */
    public function it_handles_rate_limit_exceptions()
    {
        $request = Request::create('/api/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        
        $exception = new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(60, 'Too many requests');
        
        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });
        
        $this->assertEquals(429, $response->getStatusCode());
        
        $data = $response->getData(true);
        $this->assertEquals('TOO_MANY_REQUESTS', $data['error']['code']);
    }
}