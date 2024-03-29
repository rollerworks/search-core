<?php

declare(strict_types=1);

/*
 * This file is part of the RollerworksSearch package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Rollerworks\Component\Search\Tests\Extension\Core\Type;

use Rollerworks\Component\Search\Extension\Core\ChoiceList\View\ChoiceView;
use Rollerworks\Component\Search\Extension\Core\Type\TimezoneType;
use Rollerworks\Component\Search\FieldSetView;
use Rollerworks\Component\Search\Test\FieldTransformationAssertion;
use Rollerworks\Component\Search\Test\SearchIntegrationTestCase;
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * @internal
 */
final class TimezoneTypeTest extends SearchIntegrationTestCase
{
    protected function setUp(): void
    {
        IntlTestHelper::requireIntl($this, '70.1');

        parent::setUp();
    }

    /** @test */
    public function currencies_are_selectable(): void
    {
        $field = $field = $this->getFactory()->createField('choice', TimezoneType::class);
        $field->finalizeConfig();

        FieldTransformationAssertion::assertThat($field)
            ->withInput('Kinshasa', 'Africa/Kinshasa')
            ->successfullyTransformsTo('Africa/Kinshasa')
            ->andReverseTransformsTo('Kinshasa', 'Africa/Kinshasa')
        ;

        $view = $field->createView(new FieldSetView());

        $choices = $view->vars['choices'];

        self::assertArrayHasKey('Africa', $choices);
        self::assertContainsEquals(new ChoiceView('Africa/Kinshasa', 'Africa/Kinshasa', 'Kinshasa'), $choices['Africa']);

        self::assertArrayHasKey('America', $choices);
        self::assertContainsEquals(new ChoiceView('America/New_York', 'America/New_York', 'New York'), $choices['America']);
    }
}
