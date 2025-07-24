<?php

function getUserData($userId, $client) {
    $request = new HttpRequest(
        url: "https://api.example.com/users/{$userId}",
        method: 'GET',
        headers: ['Accept' => 'application/json'],
        body: [],
        options: []
    );

    try {
        $response = $client->withRequest($request)->get();
        return json_decode($response->body(), true);
    } catch (RequestException $e) {
        // Log the error
        error_log("Failed to get user data: {$e->getMessage()}");

        // Return cached data if available
        $cachedData = $this->cache->get("user_{$userId}");
        if ($cachedData) {
            return $cachedData;
        }

        // Return minimal user data
        return [
            'id' => $userId,
            'name' => 'Unknown User',
            'is_fallback' => true,
        ];
    }
}
