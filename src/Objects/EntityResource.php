<?php
namespace CarloNicora\Minimalism\Services\JsonDataMapper\Objects;

use CarloNicora\Minimalism\Services\JsonDataMapper\Objects\Traits\LinksTrait;

class EntityResource
{
    use LinksTrait;

    /** @var string  */
    private string $type;

    /** @var EntityField  */
    private EntityField $id;

    /** @var array|null  */
    private ?array $attributes=null;

    /** @var string|null  */
    private ?string $tableName=null;

    /** @var array|null  */
    private ?array $relationships=null;

    /**
     * EntityResource constructor.
     * @param array $resource
     */
    public function __construct(array $resource)
    {
        $this->type = $resource['type'];

        $this->id = new EntityField($this, 'id', $resource['id'], true);

        if (array_key_exists('$databaseTable', $resource)){
            $this->tableName = $resource['$databaseTable'];
        }

        if (array_key_exists('attributes', $resource) && count($resource['attributes']) > 0){
            $this->attributes = [];
            foreach ($resource['attributes'] ?? [] as $attributeName=>$attribute) {
                $this->attributes[] = new EntityField($this, $attributeName, $attribute);
            }
        }

        if (array_key_exists('links', $resource) && count($resource['links']) > 0){
            $this->addLinks($resource['links']);
        }

        if (array_key_exists('relationships', $resource) && count($resource['relationships']) > 0){
            $this->relationships = [];
            foreach ($resource['relationships'] ?? [] as $relationshipName=>$relationship) {
                $this->relationships[$relationshipName] = new EntityRelationship($relationshipName, $relationship);
            }
        }
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $fieldName
     * @return EntityField|null
     */
    public function getField(string $fieldName) : ?EntityField
    {
        if ($this->id->getName() === $fieldName){
            return $this->id;
        }

        /** @var EntityField $field */
        foreach ($this->attributes ?? [] as $field){
            if ($field->getName() === $fieldName){
                return $field;
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public function getTable() : string
    {
        return $this->tableName;
    }

    /**
     * @return EntityField
     */
    public function getId(): EntityField
    {
        return $this->id;
    }

    /**
     * @return array|null|EntityField[]
     */
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    /**
     * @param string $relationshipName
     * @param string $resourceName
     * @return EntityResource|null
     */
    public function getRelationshipResource(string $relationshipName, string $resourceName) : ?EntityResource
    {
        if ($this->relationships !== null && array_key_exists($relationshipName, $this->relationships)){
            /** @var EntityRelationship $relationship */
            $relationship = $this->relationships[$relationshipName];

            if ($relationship->getType() === $resourceName){
                return $relationship->getResource();
            }
        }

        return null;
    }

    /**
     * @param string $relationshipName
     * @return EntityRelationship|null
     */
    public function getRelationship(string $relationshipName) : ?EntityRelationship
    {
        if ($this->relationships !== null && array_key_exists($relationshipName, $this->relationships)){
            return $this->relationships[$relationshipName];
        }

        return null;
    }

    /**
     * @return array|null|EntityRelationship[]
     */
    public function getRelationships(): ?array
    {
        return $this->relationships;
    }
}