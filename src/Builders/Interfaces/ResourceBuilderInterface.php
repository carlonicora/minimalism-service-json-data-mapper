<?php
namespace CarloNicora\Minimalism\Services\JsonDataMapper\Builders\Interfaces;

use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;

interface ResourceBuilderInterface extends CallableInterface, BuilderLinksInterface
{
    /**
     * ResourceBuilderInterface constructor.
     * @param ServicesFactory $services
     */
    public function __construct(ServicesFactory $services);

    /**
     *
     */
    public function initialiseRelationships(): void;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @return string
     */
    public function getTableName() : ?string;

    /**
     * @param string $attributeName
     * @return AttributeBuilderInterface|null
     */
    public function getAttribute(string $attributeName) : ?AttributeBuilderInterface;

    /**
     * @return array
     */
    public function getAttributes(): array;

    /**
     * @param string $relationshipName
     * @return RelationshipBuilderInterface|null
     */
    public function getRelationship(string $relationshipName) : ?RelationshipBuilderInterface;

    /**
     * @return array
     */
    public function getRelationships(): array;

    /**
     * @param array $data
     * @param bool $loadRelationships
     * @return ResourceObject
     */
    public function buildResourceObject(array $data, bool $loadRelationships = false): ResourceObject;
}