# Laravel Misbar Trading Application

A comprehensive Laravel application demonstrating real-world financial trading functionality with multiple API integrations and HTTP traffic monitoring using the Misbar SDK.

## Overview

This application showcases:
- **Paper Trading**: Integration with Alpaca Markets API for simulated trading
- **Market Data**: Real-time quotes, historical data, and technical indicators via Alpha Vantage
- **News Integration**: Financial news with sentiment analysis via News API
- **Portfolio Management**: Position tracking, performance analytics, and risk assessment
- **HTTP Monitoring**: Complete request/response capture using Misbar HTTP Capture SDK

## Features

### Trading Operations
- Submit buy/sell orders (market, limit, stop orders)
- Real-time order status tracking
- Order cancellation and modification
- Trading statistics and performance metrics

### Portfolio Management
- Real-time position tracking
- P&L calculations and performance analytics
- Portfolio diversification analysis
- Risk metrics and concentration analysis

### Market Data
- Real-time stock quotes from multiple providers
- Historical price data and candlestick charts
- Technical indicators (SMA, EMA, RSI, MACD, etc.)
- Company fundamentals and financial data
- Market status and trading hours

### News & Sentiment
- Financial news aggregation from trusted sources
- Stock-specific news filtering
- Basic sentiment analysis on news articles
- Market sentiment tracking

### HTTP Traffic Monitoring
- Complete capture of external API requests
- Sensitive data redaction (API keys, credentials)
- Request filtering and categorization
- Performance monitoring and analytics

## Architecture

```
app/
├── Http/Controllers/
│   ├── TradingController.php      # Trading operations API
│   ├── PortfolioController.php    # Portfolio management
│   ├── MarketDataController.php   # Market data endpoints
│   └── DashboardController.php    # Dashboard aggregation
├── Models/
│   ├── Trade.php                  # Trade order records
│   ├── Position.php               # Portfolio positions
│   ├── Asset.php                  # Tradeable assets
│   └── MarketData.php             # Market data cache
├── Services/
│   ├── AlpacaService.php         # Alpaca Markets integration
│   ├── MarketDataService.php     # Alpha Vantage integration
│   ├── NewsService.php           # News API integration
│   └── TradingService.php        # Trading orchestration
└── Http/Middleware/
    └── MisbarTrackingMiddleware.php # HTTP capture middleware
```

## Installation

1. **Install Dependencies**
   ```bash
   cd examples/php/laravel-misbar
   composer install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database Setup**
   ```bash
   # Create SQLite database (default)
   touch database/database.sqlite
   
   # Run migrations
   php artisan migrate
   ```

4. **Configure API Keys**
   Edit `.env` with your API credentials:
   ```env
   # Misbar Configuration
   MISBAR_BACKEND_URL=https://your-misbar-backend.com/events
   MISBAR_API_KEY=your-misbar-api-key
   
   # Alpaca Markets (Paper Trading)
   ALPACA_API_KEY=your-alpaca-key
   ALPACA_SECRET_KEY=your-alpaca-secret
   
   # Alpha Vantage (Market Data)
   ALPHA_VANTAGE_API_KEY=your-alphavantage-key
   
   # News API
   NEWS_API_KEY=your-news-api-key
   ```

## API Endpoints

### Trading Operations
```bash
# Submit a buy order
POST /api/v1/trading/orders
{
  "symbol": "AAPL",
  "quantity": 10,
  "side": "buy",
  "type": "market"
}

# Get all orders
GET /api/v1/trading/orders

# Get specific order
GET /api/v1/trading/orders/{id}

# Cancel order
DELETE /api/v1/trading/orders/{id}
```

### Portfolio Management
```bash
# Get portfolio summary
GET /api/v1/portfolio

# Get all positions
GET /api/v1/portfolio/positions

# Get position for specific symbol
GET /api/v1/portfolio/positions/AAPL

# Sync positions with broker
POST /api/v1/portfolio/positions/sync
```

### Market Data
```bash
# Get real-time quote
GET /api/v1/market-data/quote/AAPL

# Get historical data
GET /api/v1/market-data/historical/AAPL?timeframe=1Day&limit=100

# Get technical indicators
GET /api/v1/market-data/technical/AAPL?function=RSI&time_period=14

# Search symbols
GET /api/v1/market-data/search?keywords=apple
```

### Dashboard
```bash
# Get complete dashboard
GET /api/v1/dashboard

# Get analytics
GET /api/v1/dashboard/analytics
```

## API Providers

### Alpaca Markets
- **Purpose**: Paper trading and real-time market data
- **Features**: Order execution, account management, positions
- **Rate Limits**: 200 requests per minute
- **Documentation**: https://alpaca.markets/docs/

### Alpha Vantage
- **Purpose**: Historical data and technical indicators
- **Features**: Price history, technical analysis, company fundamentals
- **Rate Limits**: 5 requests per minute (free tier)
- **Documentation**: https://www.alphavantage.co/documentation/

### News API
- **Purpose**: Financial news aggregation
- **Features**: Top headlines, everything endpoint, source filtering
- **Rate Limits**: 1000 requests per day (free tier)
- **Documentation**: https://newsapi.org/docs

### CoinGecko
- **Purpose**: Cryptocurrency market data
- **Features**: Prices, market data, trending coins
- **Rate Limits**: 50 requests per minute (free tier)
- **Documentation**: https://www.coingecko.com/en/api

## Misbar Integration

The application includes comprehensive HTTP traffic monitoring:

### Middleware Configuration
```php
// Automatically captures external API requests
// Applied globally via MisbarTrackingMiddleware

// Custom filtering rules:
- Include: Financial API providers only
- Exclude: Health checks, static assets
- Redact: API keys, authentication headers
```

### Tracked Requests
- All Alpaca Markets API calls (trading, positions, market data)
- Alpha Vantage requests (quotes, historical data, technical indicators)
- News API requests (headlines, search, sentiment)
- CoinGecko cryptocurrency data requests

### Data Redaction
- API keys automatically redacted from headers and query parameters
- Sensitive trading data (account numbers, balances) filtered
- Personal information removed from logs

## Usage Examples

### Basic Trading Workflow
```bash
# 1. Check market status
curl http://localhost:8000/api/v1/market-data/status

# 2. Get quote for symbol
curl http://localhost:8000/api/v1/market-data/quote/AAPL

# 3. Submit buy order
curl -X POST http://localhost:8000/api/v1/trading/orders \
  -H "Content-Type: application/json" \
  -d '{"symbol":"AAPL","quantity":10,"side":"buy","type":"market"}'

# 4. Check portfolio
curl http://localhost:8000/api/v1/portfolio

# 5. Get trading stats
curl http://localhost:8000/api/v1/trading/stats
```

### Market Analysis
```bash
# Get technical indicators
curl "http://localhost:8000/api/v1/market-data/technical/AAPL?function=RSI"

# Get company information
curl http://localhost:8000/api/v1/market-data/company/AAPL

# Get relevant news
curl "http://localhost:8000/api/v1/market-data/news?type=stock&symbol=AAPL"

# Get crypto prices
curl "http://localhost:8000/api/v1/market-data/crypto?symbols=bitcoin,ethereum"
```

## Running the Application

```bash
# Start the development server
php artisan serve

# Access API documentation
curl http://localhost:8000/api/docs

# Health check
curl http://localhost:8000/api/v1/health
```

## Development

### Testing
```bash
# Run tests (when implemented)
php artisan test

# Run static analysis
./vendor/bin/phpstan analyse
```

### Database Management
```bash
# Fresh migration
php artisan migrate:fresh

# Seed test data
php artisan db:seed
```

### Logging
```bash
# View application logs
tail -f storage/logs/laravel.log

# View Misbar HTTP capture logs
# (Check your configured Misbar backend)
```

## Security Considerations

- **API Keys**: Never commit real API keys to version control
- **Paper Trading Only**: Application configured for paper trading by default
- **Rate Limiting**: Implement appropriate rate limiting for production use
- **Input Validation**: All inputs validated before external API calls
- **Error Handling**: Sensitive information not exposed in error messages

## Production Deployment

1. **Environment**: Use production API endpoints and credentials
2. **Database**: Switch to MySQL/PostgreSQL for production
3. **Caching**: Enable Redis for improved performance
4. **Queue System**: Use Redis/database queues for async processing
5. **Load Balancing**: Configure for multiple application instances
6. **Monitoring**: Set up comprehensive application monitoring

## Contributing

This is a demonstration application showcasing Misbar SDK integration. For production use:

1. Add comprehensive test coverage
2. Implement user authentication and authorization
3. Add rate limiting and request throttling
4. Include proper error handling and monitoring
5. Add WebSocket support for real-time updates
6. Implement proper queue processing for background tasks

## License

MIT License - This is a demonstration application for educational purposes.