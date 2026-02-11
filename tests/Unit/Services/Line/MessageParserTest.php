<?php

namespace Tests\Unit\Services\Line;

use App\Services\Line\MessageParser;
use PHPUnit\Framework\TestCase;

class MessageParserTest extends TestCase
{
    private MessageParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MessageParser();
    }

    public function test_parses_help_command(): void
    {
        $result = $this->parser->parse('/help');

        $this->assertTrue($result->isCommand());
        $this->assertEquals('help', $result->command);
    }

    public function test_parses_thai_help_command(): void
    {
        $result = $this->parser->parse('/ช่วยเหลือ');

        $this->assertTrue($result->isCommand());
        $this->assertEquals('help', $result->command);
    }

    public function test_parses_summary_commands(): void
    {
        $commands = [
            '/ยอดวันนี้' => 'summary_today',
            '/ยอดสัปดาห์' => 'summary_week',
            '/ยอดเดือนนี้' => 'summary_month',
            '/ยอดรวม' => 'summary_all',
        ];

        foreach ($commands as $input => $expected) {
            $result = $this->parser->parse($input);
            $this->assertTrue($result->isCommand(), "Failed for: {$input}");
            $this->assertEquals($expected, $result->command, "Failed for: {$input}");
        }
    }

    public function test_parses_connection_code(): void
    {
        $result = $this->parser->parse('CONNECT-ABC123');

        $this->assertTrue($result->isConnectionCode());
        $this->assertEquals('CONNECT-ABC123', $result->connectionCode);
    }

    public function test_parses_lowercase_connection_code(): void
    {
        $result = $this->parser->parse('connect-abc123');

        $this->assertTrue($result->isConnectionCode());
        $this->assertEquals('CONNECT-ABC123', $result->connectionCode);
    }

    public function test_parses_command_with_argument(): void
    {
        $result = $this->parser->parse('/ชื่อกลุ่ม บ้านเรา');

        $this->assertTrue($result->isCommand());
        $this->assertEquals('rename_group', $result->command);
        $this->assertEquals('บ้านเรา', $result->commandArgument);
    }

    public function test_unknown_command(): void
    {
        $result = $this->parser->parse('/unknowncommand');

        $this->assertTrue($result->isCommand());
        $this->assertEquals('unknown', $result->command);
    }

    public function test_unknown_message_without_user(): void
    {
        $result = $this->parser->parse('random text');

        $this->assertTrue($result->isUnknown());
    }

    public function test_looks_like_transaction(): void
    {
        $this->assertTrue($this->parser->looksLikeTransaction('อาหาร 150'));
        $this->assertTrue($this->parser->looksLikeTransaction('เงินเดือน 5000'));
        $this->assertTrue($this->parser->looksLikeTransaction('test 100.50'));
        $this->assertFalse($this->parser->looksLikeTransaction('just text'));
        $this->assertFalse($this->parser->looksLikeTransaction('/help'));
    }

    public function test_extract_amount(): void
    {
        $this->assertEquals(150.0, $this->parser->extractAmount('อาหาร 150'));
        $this->assertEquals(5000.0, $this->parser->extractAmount('เงินเดือน 5000'));
        $this->assertEquals(100.50, $this->parser->extractAmount('test 100.50'));
        $this->assertEquals(1000.0, $this->parser->extractAmount('1,000'));
        $this->assertNull($this->parser->extractAmount('just text'));
    }
}
