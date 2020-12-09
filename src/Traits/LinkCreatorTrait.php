<?php
namespace CarloNicora\Minimalism\Services\JsonDataMapper\Traits;

use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Interfaces\ResourceBuilderInterface;
use CarloNicora\Minimalism\Services\JsonDataMapper\JsonDataMapper;
use Exception;

trait LinkCreatorTrait
{
    /** @var ServicesFactory  */
    protected ServicesFactory $services;

    /** @var JsonDataMapper */
    protected JsonDataMapper $mapper;

    /**
     * @param string $url
     * @param ResourceBuilderInterface $resource
     * @param array $data
     * @param ResourceObject|null $resourceObject
     * @return string
     */
    public function buildLink(string $url, ResourceBuilderInterface $resource, array $data, ResourceObject $resourceObject=null) : string
    {
        if ($url[0] !== '%'){
            $url = $this->services->paths()->getUrl() . $url;
        }

        $linkElements = explode('%', $url);

        for ($linkElementsCounter = 1, $linkElementsCounterMax = count($linkElements); $linkElementsCounter < $linkElementsCounterMax; $linkElementsCounter += 2) {
            if (($attribute = $resource->getAttribute($linkElements[$linkElementsCounter])) !== null) {

                try {
                    $value = '';
                    if (array_key_exists($attribute->getDatabaseFieldName(), $data) && $data[$attribute->getDatabaseFieldName()] !== null){
                        $value = $data[$attribute->getDatabaseFieldName()];
                    } elseif (array_key_exists($attribute->getDatabaseFieldRelationship(), $data) && $data[$attribute->getDatabaseFieldRelationship()] !== null){
                        $value = $data[$attribute->getDatabaseFieldRelationship()];
                    } elseif ($resourceObject !== null) {
                        $value = $resourceObject->attributes->get($attribute->getDatabaseFieldName());
                    }
                } catch (Exception $e) {
                    $value = '';
                }

                if (is_int($value) && $attribute->isEncrypted() && ($encrypter = $this->mapper->getDefaultEncrypter()) !== null){
                    $value = $encrypter->encryptId($value);
                }

                $linkElements[$linkElementsCounter] = $value;
            } elseif (array_key_exists($linkElements[$linkElementsCounter], $data)){
                try {
                    if (($encrypter = $this->mapper->getDefaultEncrypter()) !== null) {
                        $linkElements[$linkElementsCounter] = $encrypter->encryptId($data[$linkElements[$linkElementsCounter]]);
                    }
                } catch (Exception $e) {
                    $linkElements[$linkElementsCounter] = $data[$linkElements[$linkElementsCounter]];
                }
                if ([$linkElementsCounter] === '') {
                    $linkElements[$linkElementsCounter] = $data[$linkElements[$linkElementsCounter]];
                }
            } else {
                $linkElements[$linkElementsCounter] = '%'.$linkElements[$linkElementsCounter].'%';
            }
        }

        return implode('', $linkElements);
    }
}