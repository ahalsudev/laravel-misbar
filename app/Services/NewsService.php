<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

class NewsService
{
    private Client $client;
    private string $newsApiKey;
    private string $newsApiUrl;
    private array $sources;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 30]);
        $config = Config::get('services.news_api');
        $this->newsApiKey = $config['api_key'];
        $this->newsApiUrl = $config['base_url'];
        $this->sources = $config['sources'];
    }

    public function getFinancialNews(array $params = []): array
    {
        $cacheKey = "financial_news_" . md5(serialize($params));
        
        return Cache::remember($cacheKey, 900, function() use ($params) {
            try {
                $queryParams = array_merge([
                    'category' => 'business',
                    'language' => 'en',
                    'sortBy' => 'publishedAt',
                    'pageSize' => 20,
                    'apiKey' => $this->newsApiKey
                ], $params);

                if (!empty($this->sources)) {
                    $queryParams['sources'] = implode(',', $this->sources);
                    unset($queryParams['category']); // Can't use both sources and category
                }

                $response = $this->client->get("{$this->newsApiUrl}/top-headlines", [
                    'query' => $queryParams
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if ($data['status'] !== 'ok') {
                    throw new \Exception("News API error: " . ($data['message'] ?? 'Unknown error'));
                }
                
                return $data;
            } catch (RequestException $e) {
                Log::error('News API error getting financial news', [
                    'error' => $e->getMessage(),
                    'params' => $params
                ]);
                throw $e;
            }
        });
    }

    public function searchNews(string $query, array $params = []): array
    {
        $cacheKey = "news_search_" . md5($query . serialize($params));
        
        return Cache::remember($cacheKey, 1800, function() use ($query, $params) {
            try {
                $queryParams = array_merge([
                    'q' => $query,
                    'language' => 'en',
                    'sortBy' => 'relevancy',
                    'pageSize' => 20,
                    'apiKey' => $this->newsApiKey
                ], $params);

                $response = $this->client->get("{$this->newsApiUrl}/everything", [
                    'query' => $queryParams
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if ($data['status'] !== 'ok') {
                    throw new \Exception("News API error: " . ($data['message'] ?? 'Unknown error'));
                }
                
                return $data;
            } catch (RequestException $e) {
                Log::error('News API error searching news', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    public function getStockNews(string $symbol): array
    {
        return $this->searchNews("$symbol stock", [
            'sortBy' => 'publishedAt',
            'from' => now()->subDays(7)->toISOString(),
            'domains' => 'bloomberg.com,reuters.com,wsj.com,marketwatch.com,finance.yahoo.com'
        ]);
    }

    public function getMarketNews(): array
    {
        return $this->searchNews('stock market OR trading OR finance', [
            'sortBy' => 'publishedAt',
            'from' => now()->subHours(24)->toISOString(),
            'language' => 'en'
        ]);
    }

    public function getCryptoNews(): array
    {
        return $this->searchNews('cryptocurrency OR bitcoin OR ethereum', [
            'sortBy' => 'publishedAt',
            'from' => now()->subHours(12)->toISOString(),
            'language' => 'en'
        ]);
    }

    public function getSentimentAnalysis(array $articles): array
    {
        $sentimentData = [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
            'total' => count($articles),
            'articles' => []
        ];

        $positiveKeywords = [
            'growth', 'profit', 'gain', 'rise', 'increase', 'bull', 'bullish', 
            'surge', 'rally', 'outperform', 'beat', 'strong', 'positive'
        ];
        
        $negativeKeywords = [
            'loss', 'decline', 'fall', 'drop', 'bear', 'bearish', 'crash', 
            'correction', 'underperform', 'miss', 'weak', 'negative', 'sell-off'
        ];

        foreach ($articles as $article) {
            $title = strtolower($article['title'] ?? '');
            $description = strtolower($article['description'] ?? '');
            $content = $title . ' ' . $description;

            $positiveScore = 0;
            $negativeScore = 0;

            foreach ($positiveKeywords as $keyword) {
                $positiveScore += substr_count($content, $keyword);
            }

            foreach ($negativeKeywords as $keyword) {
                $negativeScore += substr_count($content, $keyword);
            }

            if ($positiveScore > $negativeScore) {
                $sentiment = 'positive';
                $sentimentData['positive']++;
            } elseif ($negativeScore > $positiveScore) {
                $sentiment = 'negative';
                $sentimentData['negative']++;
            } else {
                $sentiment = 'neutral';
                $sentimentData['neutral']++;
            }

            $sentimentData['articles'][] = array_merge($article, [
                'sentiment' => $sentiment,
                'positive_score' => $positiveScore,
                'negative_score' => $negativeScore
            ]);
        }

        return $sentimentData;
    }
}