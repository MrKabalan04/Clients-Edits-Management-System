<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    /**
     * Return a listing of all clients.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Client::query()->get(),
        ]);
    }

    /**
     * Store a newly created client.
     */
    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Client created successfully.',
            'data' => $client,
        ], 201);
    }

    /**
     * Display the specified client.
     */
    public function show(Client $client): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Client retrieved successfully.',
            'data' => $client,
        ]);
    }

    /**
     * Update the specified client.
     */
    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Client updated successfully.',
            'data' => $client->fresh(),
        ]);
    }

    /**
     * Delete the specified client.
     */
    public function destroy(Client $client): JsonResponse
    {
        try {
            $client->delete();
        } catch (QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Client cannot be deleted because it has associated companies.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Client deleted successfully.',
        ]);
    }
}
