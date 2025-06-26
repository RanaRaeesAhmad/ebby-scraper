<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsImport;
use App\Exports\RefinedExport;
use App\Exports\AvailableProductsExport;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ScraperController extends Controller
{


    
    public function processExcel(Request $request)
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');
        ignore_user_abort(true); // Continue processing even if connection drops

        $request->validate([
            'excel_file' => 'required|mimes:xlsx,xls'
        ]);

        try {
            $products = Excel::toArray(new ProductsImport, $request->file('excel_file'))[0];
            $refinedData = [];
            
            foreach (array_chunk($products, 100) as $chunk) {
                $refinedData = array_merge(
                    $refinedData, 
                    $this->scrapeEbby($chunk)
                );
            }
            
            return Excel::download(
                new RefinedExport($refinedData), 
                'refined_products_'.now()->format('Ymd_His').'.xlsx'
            );
            
        } catch (\Exception $e) {
            return back()->withError('Error: '.$e->getMessage());
        }
    }
    
    

    

    protected function scrapeEbby($products)
    {
        $client = new Client([
            'base_uri' => 'https://www.ebay.co.uk/',
            'timeout' => 300,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                'Accept-Encoding' => 'gzip'
            ]
        ]);

        $refinedData = [];
        
        foreach ($products as $product) {
            set_time_limit(60);
            
            if (empty($product['asin'])) {
                continue;
            }

            $asin = trim($product['asin']);
            $yourPrice = (float)($product['ebay_price'] ?? 0);
            $currentTime = Carbon::now()->toDateTimeString();
            
            try {
                // Step 1: Initial search with Sold + Buy It Now filters
                $baseUrl = "/sch/i.html?_nkw=$asin&_sacat=0&_from=R40&_stpos=CR2+6XH&LH_BIN=1&LH_PrefLoc=1&_fcid=3&LH_ItemCondition=3&LH_Sold=1&_sop=10";
                $response = $client->get($baseUrl);
                $html = (string)$response->getBody();
                $crawler = new Crawler($html);
                
                // More strict check for "No exact matches found" scenarios
                if ($this->shouldSkipProduct($crawler)) {
                    continue;
                }
                
                // Step 2: Check if "New" filter is available and apply it
                $newFilterUrl = $this->getNewFilterUrl($crawler);
                if ($newFilterUrl) {
                    $response = $client->get($newFilterUrl);
                    $html = (string)$response->getBody();
                    $crawler = new Crawler($html);
                    
                    // Check again after applying new filter
                    if ($this->shouldSkipProduct($crawler)) {
                        continue;
                    }
                }
                
                // Step 3: Extract price from filtered results
                $ebbyPrice = $this->extractCorrectPrice($crawler);
                
                if ($ebbyPrice === null || $ebbyPrice <= 0) {
                    continue;
                }
                
                if ($yourPrice < $ebbyPrice) {
                    $refinedData[] = [
                        'ASIN' => $asin,
                        'Your Price (£)' => number_format($yourPrice, 2),
                        'Ebby Price (£)' => number_format($ebbyPrice, 2),
                        'Profit (£)' => number_format($ebbyPrice - $yourPrice, 2),
                        'URL' => "https://www.ebay.co.uk{$baseUrl}",
                        'Scraped At' => $currentTime
                    ];
                }
                
                usleep(rand(1000000, 2000000));
                
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return $refinedData;
    }

    // New strict checking method that combines all skip conditions
    protected function shouldSkipProduct(Crawler $crawler): bool
    {
        try {
            // Check for "No exact matches found" message
            $noMatches = $crawler->filter('div.srp-message__no-exact-matches, div.srp-no-results')->count() > 0;
            
            // Check for international items message
            $international = $crawler->filter('div.srp-message__results:contains("eBay international sellers")')->count() > 0;
            
            // Check for "0 results" indicator
            $zeroResults = $crawler->filter('h1.srp-controls__count-heading:contains("0 results")')->count() > 0;
            
            // Check for "did not match any items" message
            $noItemsMatch = $crawler->filter('div.srp-rail__align-message:contains("did not match any items")')->count() > 0;
            
            return $noMatches || $international || $zeroResults || $noItemsMatch;
        } catch (\Exception $e) {
            return false;
        }
    }



    // NEW: Method to find and apply "New" condition filter
    protected function getNewFilterUrl(Crawler $crawler): ?string
    {
        try {
            $newFilterNode = $crawler->filter('#w3-w1-w1-w0-w1[aria-label="Condition"] input[value="1000"]');
            
            if ($newFilterNode->count() > 0) {
                $relativeUrl = $newFilterNode->attr('href');
                return parse_url($relativeUrl, PHP_URL_PATH) . '?' . parse_url($relativeUrl, PHP_URL_QUERY);
            }
            
            return null;
            
        } catch (\Exception $e) {
            return null;
        }
    }

    // Updated price extraction with better handling
    protected function extractCorrectPrice(Crawler $crawler): ?float
    {
        try {
            // First try to get the first item's price
            $firstItem = $crawler->filter('.s-item__info')->first();
            
            if ($firstItem->count() > 0) {
                $priceNode = $firstItem->filter('.s-item__price');
                
                if ($priceNode->count() > 0) {
                    $priceText = $priceNode->text();
                    if (preg_match('/£?\s*(\d+\.\d{2})/', $priceText, $matches)) {
                        return (float)$matches[1];
                    }
                }
            }
            
            // Fallback to alternative selectors
            $selectors = [
                '.s-item__price span',
                '.s-item__detail .s-item__price',
                '.POSITIVE',
                '[itemprop="price"]'
            ];
            
            foreach ($selectors as $selector) {
                if ($crawler->filter($selector)->count() > 0) {
                    $priceText = $crawler->filter($selector)->first()->text();
                    if (preg_match('/£?\s*(\d+\.\d{2})/', $priceText, $matches)) {
                        return (float)$matches[1];
                    }
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            return null;
        }
    }




    // code for new refiner ...........................................................................

public function processRefined(Request $request)
{
        set_time_limit(0);
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');
        ignore_user_abort(true); // Continue processing even if connection drops


    $inputProducts = Excel::toArray(new \App\Imports\ProductsImport, $request->file('excel_file'))[0];
    $outputProducts = [];
    
    $client = new Client([
        'base_uri' => 'https://www.ebay.co.uk/',
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml'
        ],
        'timeout' => 60,
        'verify' => false,
        'allow_redirects' => true
    ]);

    foreach ($inputProducts as $product) {
        // 1. Preserve original data structure exactly
        $outputProduct = [
            'ASIN' => $product['ASIN'] ?? $product['asin'] ?? '',
            'Your Price (£)' => $product['Your Price (£)'] ?? $product['your_price_ps'] ??  $product['ebay_price'] ?? 0,
            'Ebby Price (£)' => $product['Ebby Price (£)'] ?? '',
            'Profit (£)' => $product['Profit (£)'] ?? '',
            'URL' => $product['URL'] ?? '',
            'Scraped At' => now()->toDateTimeString()
        ];

        if (empty($outputProduct['ASIN'])) {
            $outputProducts[] = $outputProduct;
            continue;
        }

        $asin = trim($outputProduct['ASIN']);
        $yourPrice = (float)$outputProduct['Your Price (£)'];
        
        try {
            // 2. Search with EXACT filters from your manual example
            $searchUrl = "/sch/i.html?_nkw=$asin&LH_BIN=1&LH_ItemCondition=3&LH_PrefLoc=1&_sop=15";
            $html = (string)$client->get($searchUrl)->getBody();
            $crawler = new Crawler($html);

            // 3. Get ALL product links (not search pages)
            $listings = $crawler->filter('.s-item__wrapper')->each(function (Crawler $node) {
                try {
                    $url = $node->filter('a.s-item__link')->attr('href');
                    if (strpos($url, '/itm/') === false) return null;
                    
                    $priceText = $node->filter('.s-item__price')->text();
                    preg_match('/£\s*([\d,]+\.\d{2})/', $priceText, $matches);
                    $price = isset($matches[1]) ? (float)str_replace(',', '', $matches[1]) : 0;
                    
                    return [
                        'url' => $url,
                        'price' => $price,
                        'title' => $node->filter('.s-item__title')->text('')
                    ];
                } catch (\Exception $e) {
                    return null;
                }
            });

            // 4. Filter and sort listings by price (low to high)
            $validListings = array_filter($listings);
            usort($validListings, fn($a, $b) => $a['price'] <=> $b['price']);

            // 5. Find first available product
            foreach ($validListings as $listing) {
                try {
                    $productHtml = (string)$client->get($listing['url'])->getBody();
                    
                    // STRICT availability check
                    $inStock = (strpos($productHtml, 'Add to basket') !== false) || 
                               (strpos($productHtml, 'Buy it now') !== false);
                    
                    if ($inStock) {
                        // Get FINAL price from product page
                        $productCrawler = new Crawler($productHtml);
                        $priceNode = $productCrawler->filter('.x-price-primary .ux-textspans');
                        
                        if ($priceNode->count() > 0) {
                            $priceText = $priceNode->text();
                            if (preg_match('/£\s*([\d,]+\.\d{2})/', $priceText, $matches)) {
                                $ebayPrice = (float)str_replace(',', '', $matches[1]);
                                
                                // Update only these fields
                                $outputProduct['Ebby Price (£)'] = number_format($ebayPrice, 2);
                                $outputProduct['Profit (£)'] = number_format($ebayPrice - $yourPrice, 2);
                                $outputProduct['URL'] = $listing['url'];
                                break; // Stop after first valid product
                            }
                        }
                    }
                    
                    usleep(300000); // 0.3s delay
                    
                } catch (\Exception $e) {
                    continue;
                }
            }
            
        } catch (\Exception $e) {
            // Keep original data if scraping fails
        }
        
        $outputProducts[] = $outputProduct;
        usleep(500000); // 0.5s delay between ASINs
    }
    
    return Excel::download(
        new \App\Exports\AvailableProductsExport($outputProducts),
        'refined_products_'.now()->format('Ymd_His').'.xlsx'
    );
}
    
}