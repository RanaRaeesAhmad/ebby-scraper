<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class ProcessProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $product,
        public string $tempFilePath
    ) {}

    public function handle()
    {
        $result = $this->scrapeProduct();
        if ($result) {
            file_put_contents(
                $this->tempFilePath,
                json_encode($result).PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    protected function scrapeProduct()
    {
        try {
            $client = new Client([
                'base_uri' => 'https://www.ebay.co.uk/',
                'timeout' => 20,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ]
            ]);

            $url = "/sch/i.html?_nkw={$this->product['asin']}&LH_Sold=1&LH_BIN=1&LH_ItemCondition=1000&LH_PrefLoc=1";
            $response = $client->get($url);
            $crawler = new Crawler((string)$response->getBody());

            $priceText = $crawler->filter('.s-item__price')->first()->text();
            $ebbyPrice = (float)preg_replace('/[^0-9.]/', '', $priceText);

            if ($this->product['ebay_price'] < $ebbyPrice) {
                return [
                    'ASIN' => $this->product['asin'],
                    'Your Price' => number_format($this->product['ebay_price'], 2),
                    'Ebby Price' => number_format($ebbyPrice, 2),
                    'Status' => 'PRICE_LOWER'
                ];
            }

        } catch (\Exception $e) {
            return null;
        }
    }
}