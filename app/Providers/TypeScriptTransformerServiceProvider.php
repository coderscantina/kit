<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\LaravelTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Configures spatie/typescript-transformer for `types:generate`. Every
 * #[TypeScript] Data class under app/ becomes a global `App.*` type; no
 * formatter, because prettier is not part of this stack.
 */
class TypeScriptTransformerServiceProvider extends BaseProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        // Scratch output; types:generate assembles the committed file from it.
        $scratch = storage_path('framework/cache/typescript');

        if (! is_dir($scratch)) {
            mkdir($scratch, 0755, true);
        }

        $config
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->extension(new LaravelTypeScriptTransformerExtension)
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformDirectories(app_path())
            ->outputDirectory($scratch)
            ->withoutManifest()
            ->writer(new GlobalNamespaceWriter('generated.d.ts'));
    }
}
