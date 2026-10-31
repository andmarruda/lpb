<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A code block (syntax highlighting by the application, e.g. Prism).
 */
class CodeHighlight extends WidgetDefinition
{
    public string $name = 'code-highlight';

    public ?string $label = 'Code highlight';

    public ?string $category = 'general';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('language')->maxLength(40)->default('plaintext')->label('Language')
            ->string('code')->required()->maxLength(100000)->label('Code')
            ->boolean('line_numbers')->default(false)->label('Line numbers');
    }

    public function example(): array
    {
        return ['language' => 'php', 'code' => "<?php\necho 'Hello';"];
    }
}
