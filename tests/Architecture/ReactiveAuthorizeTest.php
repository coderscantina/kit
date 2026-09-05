<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Kit\Reactive\Registry\Catalog;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\Fixtures\Reactive\ReactiveFixtures;
use Tests\TestCase;

/**
 * Every registered query and mutation has an authorize() that does
 * something: not empty, not a bare return, and not a Gate::allows() whose
 * answer is thrown away.
 */
final class ReactiveAuthorizeTest extends TestCase
{
    #[Test]
    public function every_authorize_body_acts_on_its_decision(): void
    {
        ReactiveFixtures::install();
        $catalog = app(Catalog::class);

        $classes = [...array_values($catalog->queries()), ...array_values($catalog->mutations())];
        $this->assertNotEmpty($classes);

        foreach ($classes as $class) {
            $body = $this->body(new ReflectionMethod($class, 'authorize'));

            $this->assertNotSame('', $body, "{$class}::authorize() is empty.");
            $this->assertDoesNotMatchRegularExpression('/^return\s*;$/', $body, "{$class}::authorize() only returns.");

            if (preg_match('/->allows\(|::allows\(|->denies\(|::denies\(|->check\(/', $body) === 1) {
                $this->assertMatchesRegularExpression(
                    '/throw\b|abort|authorize\(|deny/',
                    $body,
                    "{$class}::authorize() asks the gate but never acts on the answer.",
                );
            }
        }
    }

    #[Test]
    public function the_check_itself_catches_an_empty_body(): void
    {
        $this->assertSame('', $this->bodyFromSource("function authorize(): void\n{\n    // nothing\n    /* still nothing */\n}"));
        $this->assertSame('return;', $this->bodyFromSource('function authorize(): void { return; }'));
        $this->assertSame('abort(403);', $this->bodyFromSource("function authorize(): void {\n abort(403);\n}"));
    }

    private function body(ReflectionMethod $method): string
    {
        $file = (new ReflectionClass($method->getDeclaringClass()->getName()))->getFileName();
        $lines = file($file ?: '') ?: [];
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        return $this->bodyFromSource($source);
    }

    private function bodyFromSource(string $source): string
    {
        $open = strpos($source, '{');
        $close = strrpos($source, '}');
        $body = $open === false || $close === false ? '' : substr($source, $open + 1, $close - $open - 1);

        $body = preg_replace('#//.*$#m', '', $body) ?? '';
        $body = preg_replace('#/\*.*?\*/#s', '', $body) ?? '';

        return trim(preg_replace('/\s+/', ' ', $body) ?? '');
    }
}
