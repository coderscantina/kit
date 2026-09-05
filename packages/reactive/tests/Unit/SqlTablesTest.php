<?php

declare(strict_types=1);

namespace Kit\Reactive\Tests\Unit;

use Kit\Reactive\Runtime\SqlTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlTables::class)]
final class SqlTablesTest extends TestCase
{
    #[Test]
    public function it_finds_from_join_update_and_insert_targets(): void
    {
        $sql = 'select * from `messages` m inner join "channels" as c on c.id = m.channel_id left join users u on u.id = m.user_id where m.id = ?';

        $this->assertSame(['messages', 'channels', 'users'], SqlTables::extract($sql));
        $this->assertSame(['messages'], SqlTables::extract('update `messages` set `read` = 1 where id = ?'));
        $this->assertSame(['messages'], SqlTables::extract('insert into `messages` (`body`) values (?)'));
        $this->assertSame(['messages'], SqlTables::extract('delete from messages where id = ?'));
    }

    #[Test]
    public function it_skips_subqueries_at_the_keyword_and_resolves_them_at_their_own_from(): void
    {
        $sql = 'select * from (select * from `posts` where x = 1) as p join `tags` on tags.post_id = p.id';

        $this->assertSame(['posts', 'tags'], SqlTables::extract($sql));
    }

    #[Test]
    public function it_strips_schema_qualifiers_and_table_prefixes(): void
    {
        $this->assertSame(['orders'], SqlTables::extract('select * from `shop`.`orders`'));
        $this->assertSame(['orders'], SqlTables::extract('select * from `app_orders`', 'app_'));
    }

    #[Test]
    public function it_ignores_dual_and_deduplicates(): void
    {
        $this->assertSame([], SqlTables::extract('select 1 from dual'));
        $this->assertSame(['a'], SqlTables::extract('select * from a join a as b on a.id = b.id'));
    }
}
