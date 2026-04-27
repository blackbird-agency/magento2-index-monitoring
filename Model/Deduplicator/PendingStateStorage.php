<?php
declare(strict_types=1);

namespace Blackbird\IndexMonitoring\Model\Deduplicator;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Variable\Model\Variable as CustomVariable;
use Magento\Variable\Model\VariableFactory;

class PendingStateStorage
{
    private const VARIABLE_CODE = 'blackbird_index_monitoring_pending_first_seen';
    private const VARIABLE_NAME = 'Blackbird Index Monitoring - Pending first seen';

    public function __construct(
        private readonly VariableFactory $variableFactory,
        private readonly SerializerInterface $serializer
    ) {
    }

    /** @return array<string, int> viewId => Unix timestamp of first detection */
    public function getAll(): array
    {
        $variable = $this->variableFactory->create();
        $variable->setStoreId(0)->loadByCode(self::VARIABLE_CODE);

        if (!$variable->getId()) {
            return [];
        }

        $raw = (string) $variable->getValue(CustomVariable::TYPE_TEXT);
        if ($raw === '') {
            return [];
        }

        try {
            $data = $this->serializer->unserialize($raw);
            return is_array($data) ? $data : [];
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    /** @param array<string, int> $map */
    public function save(array $map): void
    {
        $variable = $this->variableFactory->create();
        $variable->setStoreId(0)->loadByCode(self::VARIABLE_CODE);

        if (!$variable->getId()) {
            $variable->setCode(self::VARIABLE_CODE);
            $variable->setName(self::VARIABLE_NAME);
        }

        $variable->setPlainValue($map ? $this->serializer->serialize($map) : '');
        $variable->setHtmlValue('');
        $variable->save();
    }
}
