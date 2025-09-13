<?php

return [
    'misbar' => [
        'backend_url' => env('MISBAR_BACKEND_URL'),
        'api_key' => env('MISBAR_API_KEY'),
        'source' => env('MISBAR_SOURCE', 'laravel-trading-app'),
        'enabled' => env('MISBAR_ENABLED', true),
        'sampling_rate' => env('MISBAR_SAMPLING_RATE', 1.0),
        'redact_sensitive_headers' => true,
        'max_body_size' => 10000,
    ],

    'alpaca' => [
        'api_key' => env('ALPACA_API_KEY'),
        'secret_key' => env('ALPACA_SECRET_KEY'),
        'base_url' => env('ALPACA_BASE_URL', 'https://paper-api.alpaca.markets'),
        'data_url' => env('ALPACA_DATA_URL', 'https://data.alpaca.markets'),
        'version' => 'v2',
        'paper_trading' => env('ALPACA_PAPER_TRADING', true),
    ],

    'alpha_vantage' => [
        'api_key' => env('ALPHA_VANTAGE_API_KEY'),
        'base_url' => 'https://www.alphavantage.co/query',
        'rate_limit' => 5, // requests per minute
    ],

    'news_api' => [
        'api_key' => env('NEWS_API_KEY'),
        'base_url' => 'https://newsapi.org/v2',
        'sources' => [
            'bloomberg',
            'financial-post',
            'fortune',
            'the-wall-street-journal',
        ],
    ],

    'coingecko' => [
        'base_url' => 'https://api.coingecko.com/api/v3',
        'rate_limit' => 50, // requests per minute for free tier
    ],
];