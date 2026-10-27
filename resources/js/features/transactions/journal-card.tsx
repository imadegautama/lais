import { BookOpenCheck, ChevronDown } from 'lucide-react';
import { Money } from '@/components/app/money';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { TransactionDetailProps } from '@/types/domain';

/** The accounting behind the transaction, closed by default (UI-PAGES C4). */
export function JournalCard({
    journal,
}: {
    journal: TransactionDetailProps['journal'];
}) {
    return (
        <Collapsible className="rounded-xl border bg-card shadow-card">
            <CollapsibleTrigger asChild>
                <Button
                    variant="ghost"
                    className="group h-auto w-full justify-between rounded-xl px-4 py-4 text-left md:px-5"
                >
                    <span className="flex items-center gap-3">
                        <BookOpenCheck
                            className="size-[18px] text-muted-foreground"
                            aria-hidden
                        />
                        <span className="flex flex-col">
                            <span className="text-base font-semibold">
                                Lihat jurnal
                            </span>
                            <span className="text-sm font-normal text-muted-foreground">
                                Catatan akuntansi di balik transaksi ini.
                            </span>
                        </span>
                    </span>
                    <ChevronDown
                        className="size-4 transition-transform group-data-[state=open]:rotate-180"
                        aria-hidden
                    />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="flex flex-col gap-4 px-4 pb-4 md:px-5 md:pb-5">
                <p className="text-sm text-muted-foreground">
                    Setiap transaksi dicatat di dua sisi. Debit dan kredit
                    selalu sama besar, jadi pembukuan tetap seimbang.
                </p>
                {journal.map((entry) => {
                    const debit = entry.lines.reduce((s, l) => s + l.debit, 0);
                    const credit = entry.lines.reduce(
                        (s, l) => s + l.credit,
                        0,
                    );

                    return (
                        <table key={entry.id} className="w-full text-sm">
                            <caption className="sr-only">
                                Jurnal {entry.description}
                            </caption>
                            <thead>
                                <tr className="border-b text-xs text-muted-foreground">
                                    <th className="py-2 text-left font-semibold">
                                        Akun
                                    </th>
                                    <th className="py-2 text-right font-semibold">
                                        Debit
                                    </th>
                                    <th className="py-2 text-right font-semibold">
                                        Kredit
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entry.lines.map((line, i) => (
                                    <tr key={i} className="border-b">
                                        <td className="py-2">
                                            <span className="num text-muted-foreground">
                                                {line.code}
                                            </span>{' '}
                                            {line.account}
                                            {line.moneyAccount && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {line.moneyAccount}
                                                </span>
                                            )}
                                        </td>
                                        <td className="num py-2 text-right">
                                            {line.debit > 0 && (
                                                <Money
                                                    value={line.debit}
                                                    size="sm"
                                                />
                                            )}
                                        </td>
                                        <td className="num py-2 text-right">
                                            {line.credit > 0 && (
                                                <Money
                                                    value={line.credit}
                                                    size="sm"
                                                />
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="font-semibold">
                                    <td className="py-2">
                                        {debit === credit
                                            ? 'Seimbang'
                                            : 'Tidak seimbang'}
                                    </td>
                                    <td className="py-2 text-right">
                                        <Money value={debit} size="sm" />
                                    </td>
                                    <td className="py-2 text-right">
                                        <Money value={credit} size="sm" />
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    );
                })}
            </CollapsibleContent>
        </Collapsible>
    );
}
