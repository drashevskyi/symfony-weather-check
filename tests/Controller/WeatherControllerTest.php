<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class WeatherControllerTest extends WebTestCase
{
    protected static $translation;

    public static function setUpBeforeClass() {
        $kernel = static::createKernel();
        $kernel->boot();
        self::$translation = $kernel->getContainer()->get('translator');
    }
    
    public function testIndex()
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $crawler = $client->request('GET', '/');
        $this->assertContains('<div class="input-group">', $crawler->html());
        $this->assertContains('<div class="col-sm-6 cities" style="visibility: hidden;">', $crawler->html());
        $client->request('GET', '/wrong_url');
        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }
    
    public function testGetWeatherAction()
    {
        $client = static::createClient();
        $apiKey = $client->getKernel()->getContainer()->getParameter('app.weather_api_key');
        $token = $client->getContainer()->get('security.csrf.token_manager')->getToken('weather_token');
        $crawler = $client->request('GET', '/');
        $testCity = 'London';
        $extract = $crawler->filter('input[name="form[_token]"]')->extract(['value']); //workaround as not able to generate token here
        $token = $extract[0];
        $formData = ['form' => ['ApiKey' => $apiKey, 'city' => $testCity, '_token' => $token]];
        $crawler = $client->request('POST', '/get_weather', $formData);
        //test url
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        //test result
        $this->assertContains(self::$translation->trans('Country'), $crawler->html());
        $this->assertContains($testCity, $crawler->html());
        //fail with wrong city
        $wrongCity = 'WRONG_CITY_NAME';
        $formDataFail = ['form' => ['ApiKey' => $apiKey, 'city' => $wrongCity, '_token' => $token]];
        $crawler = $client->request('POST', '/get_weather', $formDataFail);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertContains('empty', $crawler->html('empty'));
        //fail with wrong api
        $wrongApiKey = 'WRONG_API_KEY';
        $formDataFail = ['form' => ['ApiKey' => $wrongApiKey, 'city' => $testCity, '_token' => $token]];
        $crawler = $client->request('POST', '/get_weather', $formDataFail);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertContains('empty', $crawler->html('empty'));
    }
   
    public function testFailForm()
    {
        $client = static::createClient();
        $city = 'London';
        $crawler = $client->request('GET', '/get_alt_weather', ['city' => $city]);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertContains(self::$translation->trans('Temperature'), $crawler->html());
        //fail with wrong city
        $wrongCity = 'WRONG_CITY_NAME';
        $crawler = $client->request('GET', '/get_alt_weather', ['city' => $wrongCity]);
        $this->assertContains('empty', $crawler->html('empty'));
    }
}