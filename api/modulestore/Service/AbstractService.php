<?php

namespace Service;

use Validator\ValidatorInterface;
use Repository\RepositoryInterface;

abstract class AbstractService {
    
    /**
     * Données à valider
     * @var array
     */
    protected $data;
    
    /**
     * Validateur associé
     * @var ValidatorInterface 
     */
    protected $validator;
    
    /**
     * Repository associé
     * @var RepositoryInterface 
     */
    protected $repository;
    
    protected function validateData() {
        if(!$this->validator->validate()) {
            throw new InvalidException("Les données Json sont invalides");
        }
    }
    
    public function getInfosInJson();
}