<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get filtered users with pagination and field selection.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'gender' => 'nullable|in:male,female',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
            'fields' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        // Extract request parameters
        $gender = $request->input('gender');
        $city = $request->input('city');
        $country = $request->input('country');
        $limit = $request->input('limit', 10);
        $fields = $request->input('fields');

        // Start with a base query
        $query = User::query()
            ->with(['detail', 'location']);

        // Apply filters
        if ($gender) {
            $query->whereHas('detail', function ($q) use ($gender) {
                $q->where('gender', $gender);
            });
        }

        if ($city) {
            $query->whereHas('location', function ($q) use ($city) {
                $q->where('city', 'like', "%{$city}%");
            });
        }

        if ($country) {
            $query->whereHas('location', function ($q) use ($country) {
                $q->where('country', 'like', "%{$country}%");
            });
        }

        // Get the paginated results
        $users = $query->paginate($limit);

        // Process the results according to the fields parameter
        $processedUsers = $this->processUsers($users, $fields);

        // Return the response
        return response()->json([
            'status' => 'success',
            'total' => $users->total(),
            'per_page' => $users->perPage(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'data' => $processedUsers,
        ]);
    }

    /**
     * Process users data to include only requested fields.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator  $users
     * @param  string|null  $fields
     * @return array
     */
    private function processUsers($users, ?string $fields): array
    {
        $result = [];

        // Parse fields parameter
        $selectedFields = null;
        if ($fields) {
            $selectedFields = array_map('trim', explode(',', $fields));
        }

        foreach ($users as $user) {
            // Start with basic user information
            $userData = [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'username' => $user->username,
                'gender' => $user->detail->gender ?? null,
                'city' => $user->location->city ?? null,
                'country' => $user->location->country ?? null,
            ];

            // Add additional fields
            $additionalFields = [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->detail->phone ?? null,
                'cell' => $user->detail->cell ?? null,
                'date_of_birth' => $user->detail->date_of_birth ?? null,
                'street' => $user->location->full_street ?? null,
                'state' => $user->location->state ?? null,
                'postcode' => $user->location->postcode ?? null,
                'picture_large' => $user->detail->picture_large ?? null,
                'picture_medium' => $user->detail->picture_medium ?? null,
                'picture_thumbnail' => $user->detail->picture_thumbnail ?? null,
            ];

            // Merge additional fields
            $userData = array_merge($userData, $additionalFields);

            // Filter fields if specified
            if ($selectedFields) {
                $userData = array_intersect_key(
                    $userData, 
                    array_flip(
                        array_filter(
                            $selectedFields, 
                            function($field) use ($userData) {
                                return array_key_exists($field, $userData);
                            }
                        )
                    )
                );
            }

            $result[] = $userData;
        }

        return $result;
    }
    
}