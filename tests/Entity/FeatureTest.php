<?php

namespace App\Tests\Entity;

use App\Entity\Feature;
use PHPUnit\Framework\TestCase;

class FeatureTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $feature = new Feature();

        $feature->setCode('admin_menu_membres');
        $this->assertEquals('ADMIN_MENU_MEMBRES', $feature->getCode());

        $feature->setLabel('Gestion des Membres');
        $this->assertEquals('Gestion des Membres', $feature->getLabel());

        $feature->setCategory('admin_menu');
        $this->assertEquals('ADMIN_MENU', $feature->getCategory());

        $feature->setDescription('Accès au CRUD des membres');
        $this->assertEquals('Accès au CRUD des membres', $feature->getDescription());

        $feature->setTargetRoute('app_admin_membre_index');
        $this->assertEquals('app_admin_membre_index', $feature->getTargetRoute());

        $feature->setIcon('fas fa-users');
        $this->assertEquals('fas fa-users', $feature->getIcon());

        $feature->setSortOrder(15);
        $this->assertEquals(15, $feature->getSortOrder());
    }

    public function testNullCategoryAndDefaults(): void
    {
        $feature = new Feature();

        $this->assertNull($feature->getId());
        $this->assertEquals(0, $feature->getSortOrder());

        $feature->setCategory(null);
        $this->assertNull($feature->getCategory());
    }
}
