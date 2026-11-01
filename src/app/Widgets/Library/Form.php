<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A form posted to an endpoint of the application.
 */
class Form extends WidgetDefinition
{
    public string $name = 'form';

    public ?string $label = 'Form';

    public ?string $category = 'forms';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('fields', fn (Schema $i) => $i
                ->string('name')->required()->pattern('^[A-Za-z][A-Za-z0-9_]*$')->maxLength(60)
                ->string('label')->maxLength(200)
                ->enum('type', ['text', 'email', 'tel', 'url', 'number', 'date', 'textarea', 'select', 'checkbox', 'radio'])->default('text')
                ->boolean('required')->default(false)
                ->string('placeholder')->maxLength(200)
                ->strings('options'))->required()->label('Fields')
            ->string('submit_text')->maxLength(120)->default('Send')->label('Button text')
            ->url('action')->required()->label('Submit URL')->help('Endpoint of your application that receives the form')
            ->enum('method', ['post', 'get'])->default('post')->label('Method')
            ->string('success_message')->maxLength(500)->label('Success message');
    }

    public function example(): array
    {
        return ['fields' => [['name' => 'name', 'label' => 'Name', 'required' => true], ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true], ['name' => 'message', 'label' => 'Message', 'type' => 'textarea']], 'action' => '/contact', 'success_message' => 'Thanks! We will reply soon.'];
    }
}
