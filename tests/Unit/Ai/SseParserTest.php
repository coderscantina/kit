<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\Support\SseParser;
use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SseParser::class)]
final class SseParserTest extends TestCase
{
    #[Test]
    public function it_reads_frames_and_drops_comments_and_the_done_sentinel(): void
    {
        $body = Utils::streamFor(
            ": ping\n\n"
            ."data: {\"n\":1}\n\n"
            ."data: {\"n\":2}\n\n"
            ."data: [DONE]\n\n"
        );

        $this->assertSame([['n' => 1], ['n' => 2]], iterator_to_array(SseParser::parse($body)));
    }

    #[Test]
    public function a_frame_split_across_chunks_is_still_one_event(): void
    {
        // A JSON object cut in half mid-key, which is what a real socket does
        // and what a naive per-chunk json_decode loses.
        $stream = new BufferStream;
        $stream->write('data: {"content":"hel');
        $stream->write("lo\"}\n\ndata: {\"content\":\"!\"}\n\n");

        $this->assertSame(
            [['content' => 'hello'], ['content' => '!']],
            iterator_to_array(SseParser::parse($stream)),
        );
    }

    #[Test]
    public function it_survives_crlf_and_a_malformed_frame(): void
    {
        $body = Utils::streamFor(
            "data: {\"n\":1}\r\n\r\n"
            ."data: not json\n\n"
            ."data: {\"n\":2}\n\n"
        );

        $this->assertSame([['n' => 1], ['n' => 2]], iterator_to_array(SseParser::parse($body)));
    }
}
