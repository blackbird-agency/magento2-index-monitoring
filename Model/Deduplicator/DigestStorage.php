<?php
declare(strict_types=1);

namespace Blackbird\IndexMonitoring\Model\Deduplicator;

use Magento\Variable\Model\VariableFactory;
use Magento\Variable\Model\Variable as CustomVariable;

class DigestStorage
{
    private const VARIABLE_CODE = 'blackbird_index_monitoring_last_digest';
    private const VARIABLE_NAME = 'Blackbird Index Monitoring - Last digest';

    public function __construct(private VariableFactory $variableFactory)
    {
    }

    public function get(): string
    {
        $variable = $this->variableFactory->create();
        $variable->setStoreId(0)->loadByCode(self::VARIABLE_CODE);

        if (!$variable->getId()) {
            return '';
        }

        // Read plain text value from default scope
        return (string) $variable->getValue(CustomVariable::TYPE_TEXT);
    }

    public function save(string $digest): void
    {
        $variable = $this->variableFactory->create();
        $variable->setStoreId(0)->loadByCode(self::VARIABLE_CODE);

        if (!$variable->getId()) {
            // Create the variable if missing
            $variable->setCode(self::VARIABLE_CODE);
            $variable->setName(self::VARIABLE_NAME);
        }

        // Persist as plain text for default scope (store_id=0)
        $variable->setPlainValue($digest);
        $variable->setHtmlValue('');
        $variable->save();
    }

    public function hasChanged(string $digest): bool
    {
        return $digest !== $this->get();
    }
}
