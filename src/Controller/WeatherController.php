<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class WeatherController
 * @package AppBundle\Controller
 */
class WeatherController extends AbstractController 
{    
    /**
     *
     * @Route("/", name="index")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, TranslatorInterface $translator)
    {   
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('getWeather'))
            ->setMethod('POST')
            ->add('ApiKey', TextType::class, ['required' => true, 'attr' => ['placeholder' => $translator->trans('API Key'), 'class' => 'form-control']])
            ->add('city', TextType::class, ['attr' => ['class' => 'form-control mt-2', 'placeholder' => $translator->trans('City')]])
            ->add('submit', SubmitType::class, ['attr' => ['class' => 'btn btn-success mt-2']])
            ->getForm(); 
        
        return $this->render('weather.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    
    /**
     *
     * @Route("/get_weather", name="getWeather")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getWeatherAction(Request $request, TranslatorInterface $translator)
    {
        $info = null;
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('getWeather'))
            ->setMethod('POST')
            ->add('ApiKey', TextType::class, ['required' => true, 'attr' => ['placeholder' => $translator->trans('API Key'), 'class' => 'form-control']])
            ->add('city', TextType::class, ['attr' => ['class' => 'form-control mt-2', 'placeholder' => $translator->trans('City')]])
            ->add('submit', SubmitType::class, ['attr' => ['class' => 'btn btn-success mt-2']])
            ->getForm();
       
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $city = !is_null($formData) && array_key_exists('city', $formData) ? $formData['city'] : null;
            $key = !is_null($formData) && array_key_exists('ApiKey', $formData) ? $formData['ApiKey'] : null;
            //if required info is received as supposed
            if ($key && $city) {
                $client = HttpClient::create();
                $response = $client->request('GET', 'https://api.openweathermap.org/data/2.5/weather?q='.$city.'&appid='.$key);
                $info = $response->getStatusCode() == 200 ? $response->toArray() : null; //if response is 200
            }
        }

        return $this->render('city.html.twig', [
            'info' => $info,
        ]);
    }
    
    /**
     *
     * @Route("/get_alt_weather", name="getAlternativeWeathe")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAlternativeWeatherAction(Request $request)
    {
        $info = null;
        $client = HttpClient::create();
        $city = $request->get('city');
        $cachePool = new FilesystemAdapter('', 0, "cache"); // to save requests to alternative api, using cache
        
        if ($cachePool->hasItem($city)) {
            $info = $cachePool->getItem($city)->get();
        } else {
            $key = $this->getParameter('app.alt_weather_api_key');
            $response = $client->request('GET', 'http://api.weatherapi.com/v1/current.json?key='.$key.'&q='.$city);
            $info = $response->getStatusCode() == 200 ? $response->toArray() : null; // if response is 200
            $weatherCache = $cachePool->getItem($city);
            
            if (!$weatherCache->isHit()) {
                $weatherCache->set($info);
                $weatherCache->expiresAfter(60*60*4); // saving cache for 4h
                $cachePool->save($weatherCache);
            }
        }
        
        return $this->render('city-alt.html.twig', [
            'info' => $info,
        ]);
    }
   
}