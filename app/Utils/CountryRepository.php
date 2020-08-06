<?php

namespace App\Utils;

class CountryRepository
{
    /**
     * @var array $byName
     */
    protected $byName;

    /**
     * @var array $byAlpha3
     */
    protected $byAlpha3 = [];

    /**
     * @var array $byAlpha2
     */
    protected $byAlpha2 = [];


    /**
     * Country constructor.
     */
    public function __construct()
    {
        $this->byName = config('countries');
        foreach ($this->byName as $row) {
            $this->byAlpha2[$row['alpha2']] = $row;
            $this->byAlpha2[$row['alpha3']] = $row;
        }
    }

    /**
     * @return string
     */
    public function getAllNameVariationsAsString()
    {
        $names = implode(',', array_keys($this->byName));
        $alpha2s = implode(',', array_keys($this->byAlpha2));
        $alpha3s = implode(',', array_keys($this->byAlpha3));

        return implode(',', [$names, $alpha2s, $alpha3s]);
    }

    /**
     * @return array
     */
    public function getAllByName()
    {
        return $this->byName;
    }

    /**
     * @return array
     */
    public function getAllByAlpha3(): array
    {
        return $this->byAlpha3;
    }

    /**
     * @return array
     */
    public function getAllByAlpha2(): array
    {
        return $this->byAlpha2;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getCountry(string $name)
    {
        $all = $this->byName + $this->byAlpha2 + $this->byAlpha3;
        if (array_key_exists($name, $all)) {
            return $all[$name];
        }
        return null;
    }

    /**
     * @param string $name
     * @return null
     */
    public function getCurrency(string $name)
    {
        $country = $this->getCountry($name);
        if ($country) {
            return $country['currency'];
        }
        return null;
    }

    /**
     * @param string $name
     * @return null
     */
    public function getAlpha2(string $name)
    {
        $country = $this->getCountry($name);
        if ($country) {
            return $country['alpha2'];
        }
        return null;
    }

    /**
     * @param string $name
     * @return null
     */
    public function getAlpha3(string $name)
    {
        $country = $this->getCountry($name);
        if ($country) {
            return $country['alpha3'];
        }
        return null;
    }

    /**
     * @param string $name
     * @return null
     */
    public function getName(string $name)
    {
        $country = $this->getCountry($name);
        if ($country) {
            return $country['name'];
        }
        return null;
    }

    public function getPhonePrefix(string $name)
    {
        $country = $this->getCountry($name);
        if ($country) {
            return $country['phone'];
        }
        return null;
    }
}
