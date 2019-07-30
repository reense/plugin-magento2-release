<?php

namespace DHLParcel\Shipping\Setup;

use DHLParcel\Shipping\Model\Carrier;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    protected $configReader;
    protected $configWriter;
    /** @var EavSetup */
    protected $eavSetup;
    protected $eavSetupFactory;

    /**
     * UpgradeData constructor.
     * @param ScopeConfigInterface $configReader
     * @param WriterInterface $configWriter
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ScopeConfigInterface $configReader,
        WriterInterface $configWriter,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->configReader = $configReader;
        $this->configWriter = $configWriter;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $this->eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        if (version_compare($context->getVersion(), "1.0.1", "<")) {
            $configs = [
                'carriers/dhlparcel/label/default_extra_insurance' => 'carriers/dhlparcel/label/default_extra_assured'
            ];
            $this->updateConfigPaths($configs);
        }

        if (version_compare($context->getVersion(), "1.0.2", "<")) {
            $configs = [
                'carriers/dhlparcel/usability/bulk/print' => 'carriers/dhlparcel/usability/bulk/download'
            ];
            $this->updateConfigPaths($configs);
            $this->addProductBlacklistAttributes();
        }

        if (version_compare($context->getVersion(), "1.0.4", "<")) {
            $this->updateProductBlacklistAttributeLabels();
        }

        if (version_compare($context->getVersion(), "1.0.5", "<")) {
            $this->updateProductBlacklistServicepointAttributeSourceModel();
        }

        $setup->endSetup();
    }

    /**
     * @param $replaceConfigs
     */
    private function updateConfigPaths($replaceConfigs)
    {
        foreach ($replaceConfigs as $oldPath => $newPath) {
            if ($this->configReader->getValue($oldPath)) {
                // Replace values to new path
                $this->configWriter->save($newPath, $this->configReader->getValue($oldPath));
            }
            $this->configWriter->delete($oldPath);
        }
    }

    private function addAttributesToAttributeSets($attributeCodes = [])
    {
        $entityTypeId = $this->eavSetup->getEntityTypeId('catalog_product');
        $attributeSetIds = $this->eavSetup->getAllAttributeSetIds($entityTypeId);
        $groupName = 'DHL Parcel';

        $attributeIds = [];
        foreach ($attributeCodes as $attributeCode) {
            $attributeIds[] = $this->eavSetup->getAttributeId($entityTypeId, $attributeCode);
        }
        foreach ($attributeSetIds as $attributeSetId) {
            $this->eavSetup->addAttributeGroup($entityTypeId, $attributeSetId, $groupName, 50);
            $attributeGroupId = $this->eavSetup->getAttributeGroupId($entityTypeId, $attributeSetId, $groupName);
            // Add existing attribute to group

            foreach ($attributeIds as $attributeId) {
                $this->eavSetup->addAttributeToGroup($entityTypeId, $attributeSetId, $attributeGroupId, $attributeId, null);
            }
        }
    }

    private function addProductBlacklistAttributes()
    {
        $this->eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_SERVICEPOINT,
            [
                'type'                    => 'int',
                'backend'                 => '',
                'frontend'                => '',
                'label'                   => 'DHL Parcel blacklist servicepoint delivery in checkout',
                'input'                   => 'select',
                'class'                   => '',
                'source'                  => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'global'                  => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'                 => true,
                'required'                => false,
                'user_defined'            => false,
                'default'                 => 0,
                'searchable'              => false,
                'filterable'              => false,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => false,
                'unique'                  => false,
                'apply_to'                => ''
            ]
        );
        $this->eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_GENERAL,
            [
                'type'                    => 'varchar',
                'backend'                 => \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
                'frontend'                => '',
                'label'                   => 'DHL Parcel blacklist service options in checkout',
                'input'                   => 'multiselect',
                'class'                   => '',
                'source'                  => \DHLParcel\Shipping\Model\Entity\Attribute\Source\BlackList::class,
                'global'                  => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'                 => true,
                'required'                => false,
                'user_defined'            => false,
                'default'                 => '',
                'searchable'              => false,
                'filterable'              => false,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => false,
                'unique'                  => false,
                'apply_to'                => ''
            ]
        );
        $this->addAttributesToAttributeSets([Carrier::BLACKLIST_SERVICEPOINT, Carrier::BLACKLIST_GENERAL]);
    }

    private function updateProductBlacklistAttributeLabels()
    {
        $this->eavSetup->updateAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_SERVICEPOINT,
            'label',
            'Do not show delivery methods with ServicePoint service option in the checkout'
        );

        $this->eavSetup->updateAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_SERVICEPOINT,
            'label',
            'Do not show delivery methods with the following service options in the checkout'
        );
    }

    private function updateProductBlacklistServicepointAttributeSourceModel()
    {
        $this->eavSetup->updateAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_SERVICEPOINT,
            'source_model',
            \DHLParcel\Shipping\Model\Entity\Attribute\Source\NoYes::class
        );
    }
}
