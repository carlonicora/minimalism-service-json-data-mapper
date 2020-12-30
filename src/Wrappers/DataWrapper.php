<?php
namespace CarloNicora\Minimalism\Services\JsonApi\Wrappers;

use CarloNicora\Minimalism\Services\JsonApi\Builders\Facades\FunctionFacade;
use CarloNicora\Minimalism\Services\JsonApi\Builders\Factories\FunctionFactory;
use CarloNicora\Minimalism\Services\JsonApi\Factories\DataReadersFactory;
use CarloNicora\Minimalism\Services\JsonApi\Proxies\ServicesProxy;
use Exception;

class DataWrapper
{
    /** @var FunctionFacade|null  */
    private ?FunctionFacade $function=null;

    /** @var array|null  */
    private ?array $parameters=null;

    /** @var bool  */
    private bool $isSingle=false;

    /**
     * Parameter constructor.
     * @param ServicesProxy $servicesProxy
     */
    public function __construct(
        private ServicesProxy $servicesProxy,
    ){}

    /**
     * @return array|null
     * @throws Exception
     */
    public function loadData() : ?array
    {
        $dataReadersFactory = new DataReadersFactory(
            servicesProxy: $this->servicesProxy,
        );

        $function = $dataReadersFactory->create(
            FunctionFactory::buildFromTableName(
                $this->function->getTableName(),
                $this->function->getFunctionName()),
            $this->parameters
        );
        if ($this->isSingle) {
            $response = $function->getSingle();
        } else {
            $response = $function->getList();
        }

        return $response;
    }

    /**
     * @param FunctionFacade $function
     */
    public function setFunction(FunctionFacade $function): void
    {
        $this->function = $function;
    }

    /**
     * @param array|null $parameters
     */
    public function setParameters(?array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @param bool $isSingle
     */
    public function setIsSingle(bool $isSingle): void
    {
        $this->isSingle = $isSingle;
    }
}