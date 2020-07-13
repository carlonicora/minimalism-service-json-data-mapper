<?php
namespace CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Abstracts;

use CarloNicora\JsonApi\Objects\Relationship;
use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Core\Events\MinimalismInfoEvents;
use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Interfaces\EncrypterInterface;
use CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Factories\AttributeBuilderFactory;
use CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Factories\RelationshipBuilderFactory;
use CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Factories\ResourceBuilderFactory;
use CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Interfaces\AttributeBuilderInterface;
use CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Interfaces\RelationshipBuilderInterface;
use CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Interfaces\RelationshipTypeInterface;
use CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Interfaces\ResourceBuilderInterface;
use CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Traits\LinkBuilderTrait;
use CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Traits\ReadFunctionTrait;
use CarloNicora\Minimalism\Services\JsonDataMapper\Events\JsonDataMapperErrorEvents;
use CarloNicora\Minimalism\Services\JsonDataMapper\Interfaces\LinkCreatorInterface;
use CarloNicora\Minimalism\Services\JsonDataMapper\Interfaces\TransformatorInterface;
use CarloNicora\Minimalism\Services\JsonDataMapper\JsonDataMapper;
use CarloNicora\Minimalism\Services\MySQL\Exceptions\DbRecordNotFoundException;
use Exception;

abstract class AbstractResourceBuilder implements ResourceBuilderInterface, LinkCreatorInterface
{
    use LinkBuilderTrait;
    use ReadFunctionTrait;

    /** @var ServicesFactory|null  */
    private static ?ServicesFactory $staticServices=null;

    /** @var JsonDataMapper|null  */
    private static ?JsonDataMapper $staticMapper=null;

    /** @var array  */
    private static array $fieldCache = [];

    /** @var array  */
    private static array $relationshipFieldCache = [];

    /** @var string */
    public string $type;

    /** @var string|null */
    public ?string $tableName = null;

    /** @var array|AttributeBuilderInterface[] */
    protected array $attributes = [];

    /** @var array|RelationshipBuilderInterface[] */
    protected array $relationships = [];

    /** @var AttributeBuilderFactory */
    private AttributeBuilderFactory $attributeBuilderFactory;

    /** @var RelationshipBuilderFactory */
    private RelationshipBuilderFactory $relationshipBuilderFactory;

    /** @var string|null  */
    protected ?string $dataCache=null;

    /** @var string|null  */
    protected ?string $resourceCache=null;

    /**
     * @param ServicesFactory $services
     * @throws Exception
     */
    public static function initialise(ServicesFactory $services) : void
    {
        self::$staticServices = $services;
        self::$staticMapper = $services->service(JsonDataMapper::class);
    }

    /**
     * AbstractResourceBuilder constructor.
     * @param ServicesFactory $services
     * @throws Exception
     */
    public function __construct(ServicesFactory $services)
    {
        $this->attributeBuilderFactory = new AttributeBuilderFactory($services, $this);
        $this->relationshipBuilderFactory = new RelationshipBuilderFactory($services);

        $this->services = $services;
        $this->mapper = $services->service(JsonDataMapper::class);

        $this->setAttributes();
        $this->services->logger()->info()->log(new MinimalismInfoEvents(9, null, 'Resource Object Attributes Created (' . get_class($this) . ')'));
        $this->setLinks();
        $this->services->logger()->info()->log(new MinimalismInfoEvents(9, null, 'Resource Object Links Created (' . get_class($this) . ')'));
    }

    /**
     * @return string|null
     */
    public function getDataCacheName(): ?string
    {
        return $this->dataCache;
    }

    /**
     * @return string|null
     */
    public function getResourceCacheName(): ?string
    {
        return $this->resourceCache;
    }


    /**
     * @return array|AttributeBuilderInterface[]
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return array
     */
    public function getRelationships(): array
    {
        return $this->relationships;
    }

    /**
     *
     * @throws Exception
     */
    public function initialiseRelationships(): void
    {
        $this->setRelationships();
        $this->services->logger()->info()->log(new MinimalismInfoEvents(9, null, 'Resource Object Relationships Created (' . get_class($this) . ')'));
    }

    /**
     * @return AttributeBuilderInterface
     * @throws Exception
     */
    public static function attributeId() : AttributeBuilderInterface
    {
        return self::attribute('id');
    }

    /**
     * @param string $attributeName
     * @return AttributeBuilderInterface
     * @throws Exception
     */
    protected static function attribute(string $attributeName) : AttributeBuilderInterface
    {
        if (($response = self::$staticMapper->getCache()->getAttributeBuilder(static::class, $attributeName)) === null)
        {
            $resourceBuilderFactory = new ResourceBuilderFactory(self::$staticServices);
            $resourceBuilder = $resourceBuilderFactory->createResourceBuilder(static::class);

            $response = $resourceBuilder->getAttribute($attributeName);
        }

        if ($response === null) {
            self::$staticServices->logger()->error()->log(
                JsonDataMapperErrorEvents::NO_ATTRIBUTE_FOUND(static::class, $attributeName)
            )->throw();
        }

        return clone $response;
    }

    /**
     * @param string $relationshipName
     * @return AttributeBuilderInterface
     * @throws Exception
     */
    protected static function relationship(string $relationshipName) : AttributeBuilderInterface
    {
        $resourceBuilderFactory = new ResourceBuilderFactory(self::$staticServices);
        $resourceBuilder = $resourceBuilderFactory->createResourceBuilder(static::class);
        /** @var RelationshipBuilderInterface $relationshipBuilder */
        $relationshipBuilder = $resourceBuilder->relationships[$relationshipName];

        return clone $relationshipBuilder->getAttribute();
    }

    /**
     *
     */
    abstract protected function setAttributes(): void;

    /**
     *
     */
    abstract protected function setLinks(): void;

    /**
     *
     */
    abstract protected function setRelationships(): void;

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }


    /**
     * @return string|null
     */
    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    /**
     * @param string $relationshipName
     * @return RelationshipBuilderInterface|null
     */
    public function getRelationship(string $relationshipName): ?RelationshipBuilderInterface
    {
        if (!array_key_exists($relationshipName, $this->relationships)) {
            return null;
        }

        return $this->relationships[$relationshipName];
    }

    /**
     * @param string $attributeName
     * @return AttributeBuilderInterface|null
     */
    public function getAttribute(string $attributeName): ?AttributeBuilderInterface
    {
        if (array_key_exists($attributeName, $this->attributes)){
            return $this->attributes[$attributeName];
        }

        if (array_key_exists($attributeName, $this->relationships)){
            return $this->relationships[$attributeName]->getAttribute();
        }

        return null;
    }


    /**
     * @param string $attributeName
     * @return AttributeBuilderInterface
     */
    final protected function generateAttribute(string $attributeName): AttributeBuilderInterface
    {
        $response = $this->attributeBuilderFactory->create($attributeName);

        $this->attributes[$attributeName] = $response;

        return $response;
    }

    /**
     * @param string $name
     * @param int $type
     * @param AttributeBuilderInterface $attribute
     * @param string $fieldName
     * @param string|null $manyToManyRelationshipTableName
     * @param string|null $manyToManyRelationshipField
     * @return RelationshipBuilderInterface
     * @throws Exception
     */
    final protected function generateRelationship(string $name, int $type, AttributeBuilderInterface $attribute, string $fieldName=null, string $manyToManyRelationshipTableName=null, string $manyToManyRelationshipField=null): RelationshipBuilderInterface
    {
        $response = $this->relationshipBuilderFactory->create($name, $type, $attribute, $fieldName, $manyToManyRelationshipTableName, $manyToManyRelationshipField);

        $this->relationships[$name] = $response;

        return $response;
    }

    /**
     * @param array $data
     * @param int $loadRelationshipsLevel
     * @return ResourceObject
     * @throws Exception
     */
    final public function buildResourceObject(array $data, int $loadRelationshipsLevel=0): ResourceObject
    {
        $response = new ResourceObject($this->type);

        $this->buildAttributes($response, $data);
        $this->buildLinks($this, $this, $response->links, $data, $response);

        if ($loadRelationshipsLevel > 0){
            $this->buildRelationships($response, $data, $loadRelationshipsLevel);
        }

        return $response;
    }

    /**
     * @param ResourceObject $response
     * @param array $data
     * @param int $loadRelationshipsLevel
     * @throws Exception
     */
    private function buildRelationships(ResourceObject $response, array $data, int $loadRelationshipsLevel=0): void
    {
        foreach ($this->relationships as $relationship){
            $relation = new Relationship();

            /** @var JsonDataMapper $mapper */
            $mapper = $this->mapper;

            try {
                if ($relationship->getReadFunction() !== null) {
                    if (($values = $relationship->getReadValues()) === null){
                        $values = [$data[$relationship->getAttribute()->getDatabaseFieldRelationship()]];
                    }

                        $relation->resourceLinkage->resources = $mapper->generateResourceObjectsByFunction(
                            $relationship->getResourceBuilderName(),
                            null,
                            $relationship->getReadFunction(),
                            $values,
                            $loadRelationshipsLevel-1
                        );
                    if ($relationship->getAttribute()->getDatabaseFieldRelationship() !== $relationship->getAttribute()->getDatabaseFieldName()){
                        $data[$relationship->getAttribute()->getDatabaseFieldName()] = $relation->resourceLinkage->resources[0]->id ?? null;
                    }
                } else {
                    switch ($relationship->getType()) {
                        case RelationshipTypeInterface::RELATIONSHIP_ONE_TO_ONE:
                            if ($data[$relationship->getAttribute()->getDatabaseFieldRelationship()] === null) {
                                continue 2;
                            }
                            $relation->resourceLinkage->resources = $mapper->generateResourceObjectByFieldValue(
                                $relationship->getResourceBuilderName(),
                                null,
                                $relationship->getAttribute(),
                                $data[$relationship->getAttribute()->getDatabaseFieldRelationship()],
                                $loadRelationshipsLevel-1
                            );
                            break;
                        case RelationshipTypeInterface::RELATIONSHIP_ONE_TO_MANY:
                            $relation->resourceLinkage->resources = $mapper->generateResourceObjectByFieldValue(
                                $relationship->getResourceBuilderName(),
                                null,
                                $relationship->getAttribute(),
                                $data[$relationship->getAttribute()->getDatabaseFieldRelationship()],
                                $loadRelationshipsLevel-1
                            );
                            break;
                        case RelationshipTypeInterface::RELATIONSHIP_MANY_TO_MANY:
                            $relation->resourceLinkage->resources = $mapper->generateResourceObjectsByFunction(
                                $relationship->getResourceBuilderName(),
                                null,
                                'getFirstLevelJoin',
                                [
                                    $relationship->getManyToManyRelationshipTableName(),
                                    $relationship->getAttribute()->getDatabaseFieldRelationship(),
                                    $relationship->getManyToManyRelationshipField(),
                                    $data[$relationship->getAttribute()->getDatabaseFieldRelationship()]
                                ],
                                $loadRelationshipsLevel-1
                            );

                            break;
                    }
                }
            } catch (DbRecordNotFoundException $exception) {
                // No relationship record - no problem
            }

            $this->buildLinks($relationship, $this, $relation->links, $data);

            $response->relationships[$relationship->getName()] = $relation;
        }
    }

    /**
     * @param ResourceObject $response
     * @param array $data
     * @throws Exception
     */
    private function buildAttributes(ResourceObject $response, array $data): void
    {
        foreach ($this->attributes as $attribute) {
            if (!$attribute->isWriteOnly()){
                if ($attribute->getName() === 'id'){
                    $response->id = $this->getAttributeValue($attribute, $data);
                } else {
                    $response->attributes->add(
                        $attribute->getName(),
                        $this->getAttributeValue($attribute, $data)
                    );
                }
            }
        }
    }

    /**
     * @param AttributeBuilderInterface $attribute
     * @param array $data
     * @return mixed
     */
    private function getAttributeValue(AttributeBuilderInterface $attribute, array $data)
    {
        $response = $data[$attribute->getDatabaseFieldName()] ?? null;

        if ($attribute->isEncrypted()){
            /** @var EncrypterInterface $encrypter */
            if ($response !== null && ($encrypter = $this->mapper->getDefaultEncrypter()) !== null) {
                $response = $encrypter->encryptId(
                    $response
                );
            }
        } elseif ($attribute->getTransformationClass() !== null && $attribute->getTransformationMethod() !== null) {
            $transformatorClass = $attribute->getTransformationClass();

            /** @var TransformatorInterface $transformator */
            $transformator = new $transformatorClass($this->services);
            $response = $transformator->transform(
                $attribute->getTransformationMethod(),
                $data,
                $attribute->getDatabaseFieldName()
            );
        } elseif (($type = $attribute->getType()) !== null) {
            $response = $type->transformValue($response);
        }

        return $response;
    }
}