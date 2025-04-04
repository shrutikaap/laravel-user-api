<?php

namespace App\Console\Commands;

use App\Models\UserDetail;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Carbon\Carbon;


class FetchRandomUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:fetch {count=5 : Number of users to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch random users from the randomuser.me API and store them in the database';

    /**
     * The API endpoint for random user data.
     *
     * @var string
     */
    protected $apiEndpoint = 'https://randomuser.me/api/';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $count = $this->argument('count');
        $this->info("Fetching {$count} random users...");
        
        $successCount = 0;
        
        // Make separate API calls as per requirement
        for ($i = 0; $i < $count; $i++) {
            try {
                // Make API request to fetch a single random user
                $response = Http::get($this->apiEndpoint);
                
                // Check if the request was successful
                if ($response->successful()) {
                    $userData = $response->json()['results'][0];
                    
                    // Process and store the user data
                    if ($this->storeUserData($userData)) {
                        $successCount++;
                        $this->info("User #{$i} stored successfully");
                    } else {
                        $this->error("Failed to store user #{$i}");
                    }
                } else {
                    $this->error("API request #{$i} failed with status: " . $response->status());
                    Log::error("Random User API request failed", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            } catch (\Exception $e) {
                $this->error("Exception occurred during API request #{$i}: " . $e->getMessage());
                Log::error("Exception in FetchRandomUsers command", [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        $this->info("Completed: {$successCount}/{$count} users fetched and stored");
        return 0;
    }
    
    /**
     * Store the user data in the database.
     *
     * @param array $userData The user data from the API
     * @return bool True if successful, false otherwise
     */
    private function storeUserData($userData)
    {
        try {
            // Begin a database transaction
            \DB::beginTransaction();
            
            // Create the user record
            $user = User::create([
                'first_name' => $userData['name']['first'],
                'last_name' => $userData['name']['last'],
                'email' => $userData['email'],
                'username' => $userData['login']['username'],
            ]);
            
            // Create the user detail record
            UserDetail::create([
                'user_id' => $user->id,
                'gender' => $userData['gender'],
                'date_of_birth' => Carbon::parse($userData['dob']['date'])->format('Y-m-d H:i:s'),
                'phone' => $userData['phone'],
                'cell' => $userData['cell'],
                'picture_large' => $userData['picture']['large'],
                'picture_medium' => $userData['picture']['medium'],
                'picture_thumbnail' => $userData['picture']['thumbnail'],
            ]);
            
            // Create the location record
            Location::create([
                'user_id' => $user->id,
                'street_number' => $userData['location']['street']['number'],
                'street_name' => $userData['location']['street']['name'],
                'city' => $userData['location']['city'],
                'state' => $userData['location']['state'],
                'country' => $userData['location']['country'],
                'postcode' => $userData['location']['postcode'],
                'latitude' => $userData['location']['coordinates']['latitude'],
                'longitude' => $userData['location']['coordinates']['longitude'],
            ]);
            
            // Commit the transaction
            \DB::commit();
            
            return true;
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            \DB::rollBack();
            
            Log::error("Failed to store user data", [
                'message' => $e->getMessage(),
                'userData' => $userData,
                'trace' => $e->getTraceAsString()
            ]);
            echo $e->getMessage();
            die();
            return false;
        }
    }
}