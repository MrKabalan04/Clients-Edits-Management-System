<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    /**
     * Return a listing of all companies.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Company::query()->with('client')->get(),
        ]);
    }

    /**
     * Store a newly created company.
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::query()->create($request->validated());

        $company->loadMissing('client');

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully.',
            'data' => $company,
        ], 201);
    }

    /**
     * Display the specified company.
     */
    public function show(Company $company): JsonResponse
    {
        $company->loadMissing('client');

        return response()->json([
            'success' => true,
            'message' => 'Company retrieved successfully.',
            'data' => $company,
        ]);
    }

    /**
     * Update the specified company.
     */
    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $company->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully.',
            'data' => $company->fresh()->loadMissing('client'),
        ]);
    }

    /**
     * Delete the specified company.
     */
    public function destroy(Company $company): JsonResponse
    {
        try {
            $company->delete();
        } catch (QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Company cannot be deleted because it has associated projects.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully.',
        ]);
    }
}
