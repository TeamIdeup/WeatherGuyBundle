<?php

/* vim: set expandtab tabstop=4 shiftwidth=4: */
namespace Ideup\WeatherGuyBundle\Model\Manager;

use 
    Doctrine\ORM\EntityManager,
    Ideup\WeatherGuyBundle\Geocoding\IGeocodingAdapter,
    Ideup\WeatherGuyBundle\Entity\WeatherStation,
    
    Ideup\WeatherGuyBundle\Model\IWeatherFinder
;

/**
 * Description of WeatherLocationManager
 *
 * @author Fco Javier Aceituno <fco.javier.aceituno@gmail.com>
 */
class WeatherStationManager implements IWeatherFinder
{
    const DEFAULT_COUNTRY = "España";
    
    protected $geocoding;
    
    protected $em;
    
    public function __construct(EntityManager $em, IGeocodingAdapter $geo)
    {
        $this->geocoding    = $geo;
        $this->em           = $em;
    }
    
    public function create($name, $locality, $city, $country = self::DEFAULT_COUNTRY)
    {
        $address = $locality . " " . $city . ", " . $country;
        $geoLocation = $this->geocoding->getLocation($address);
        
        $data = array(
            'name'      => $name,
            'city'      => $city,
            'locality'  => $locality,
            'country'   => $country,
            'latitude'  => $geoLocation->getLatitude(),
            'longitude' => $geoLocation->getLongitude()
        );
        
        $weatherStation = new WeatherStation();
        $weatherStation->fromArray($data);

        return $weatherStation;
    }
    
    public function getEntityManager()
    {
        return $this->em;
    }
    
    public function getRepository()
    {
        return $this->getEntityManager()->getRepository('IdeupWeatherGuyBundle:WeatherStation');
    }
    
    public function save(WeatherStation $weatherStation)
    {
        $this->getEntityManager()->persist($weatherStation);
        $this->getEntityManager()->flush();
    }
    
    public function delete(WeatherStation $weatherStation)
    {
        $this->getEntityManager()->detach($weatherStation);
        $this->getEntityManager()->flush();
    }
    
    public function findStationByName($name)
    {
        return $this->getRepository()->findOneBy(array('name' => $name));
    }
    
    public function findStationsBy(array $criteria)
    {
        return $this->getRepository()->findBy($criteria);
    }
    
    public function existStationBy(array $criteria)
    {
        return count($this->findStationsBy($criteria)) > 0;
    }

    public function findWeatherLocation($address, $distance)
    {
        $location = $this->geocoding->getLocation($address);
        
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping;
        $rsm->addEntityResult('IdeupWeatherGuyBundle:WeatherStation', 'ws');
        $rsm->addScalarResult('id', 'id');
        
        $query = $this
            ->getEntityManager()->createNativeQuery(
                '   SELECT ws.id as id, SQRT(
                        POW(69.1 * (ws.latitude - :latitude), 2) +
                        POW(69.1 * (:longitude - ws.longitude) * COS(ws.latitude / 57.3), 2)
                    ) AS distance
                    FROM weather_guy_weather_station ws HAVING distance < :distance ORDER BY distance LIMIT 0,1;
                ', $rsm)
            ->setParameters(array(
                'latitude'  => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
                'distance'  => $distance
            ))
        ;
        
        return $this->getRepository()->findOneBy($query->getSingleResult());
    }
}