<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Services\Ai\Registry\AiCatalog;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Every registered AI action has an authorize() that does something. An
 * action reaches a paid provider on behalf of whoever asked, so an empty
 * body here is an open endpoint with a bill attached.
 */
final class AiAuthorizeTest extends TestCase
{
    #[Test]
    public function every_action_authorizes(): void
    {
        $classes = array_values(app(AiCatalog::class)->actions());

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

    private function body(ReflectionMethod $method): string
    {
        $file = (new ReflectionClass($method->getDeclaringClass()->getName()))->getFileName();
        $lines = file($file ?: '') ?: [];
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $open = strpos($source, '{');
        $close = strrpos($source, '}');
        $body = $open === false || $close === false ? '' : substr($source, $open + 1, $close - $open - 1);

        $body = preg_replace('#//.*$#m', '', $body) ?? '';
        $body = preg_replace('#/\*.*?\*/#s', '', $body) ?? '';

        return trim(preg_replace('/\s+/', ' ', $body) ?? '');
    }
}
