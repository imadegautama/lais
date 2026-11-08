import { Head, Link, useForm } from '@inertiajs/react';
import {
    Check,
    CircleCheck,
    CirclePause,
    Copy,
    ExternalLink,
    Globe,
    Settings2,
    Store,
} from 'lucide-react';
import { toast } from 'sonner';
import { LabelBadge } from '@/components/app/badges';
import { ChoiceGroup } from '@/components/app/choice-group';
import { Field } from '@/components/app/field';
import { SectionCard } from '@/components/app/section-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useClipboard } from '@/hooks/use-clipboard';
import { edit, update } from '@/routes/business';
import { index as orders } from '@/routes/orders';
import { settings } from '@/routes';
import { BusinessCategory, businessCategoryLabels } from '@/types/enums';

type Props = {
    business: {
        name: string;
        category: BusinessCategory | null;
        shopUrl: string;
        open: boolean;
    };
};

/** Pengaturan > Usaha (UI-PAGES C20). */
export default function BusinessSettings({ business }: Props) {
    const form = useForm({
        name: business.name,
        category: business.category,
    });
    const [copied, copy] = useClipboard();

    return (
        <>
            <Head title="Usaha" />

            <SectionCard
                title="Profil usaha"
                description="Nama ini tampil di halaman toko, nota, dan laporan."
                icon={Store}
            >
                <form
                    className="flex flex-col gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(update(), { preserveScroll: true });
                    }}
                >
                    <Field
                        label="Nama usaha"
                        htmlFor="business-name"
                        error={form.errors.name}
                    >
                        <Input
                            id="business-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            autoComplete="organization"
                            aria-invalid={
                                Boolean(form.errors.name) || undefined
                            }
                        />
                    </Field>

                    <div className="flex flex-col gap-1.5">
                        <span
                            id="business-category"
                            className="text-sm font-medium"
                        >
                            Jenis usaha
                        </span>
                        <ChoiceGroup
                            labelledBy="business-category"
                            value={form.data.category ?? ''}
                            options={Object.values(BusinessCategory).map(
                                (value) => ({
                                    value,
                                    label: businessCategoryLabels[value],
                                }),
                            )}
                            onChange={(category) =>
                                form.setData('category', category)
                            }
                            wrap
                        />
                        <InputError message={form.errors.category} />
                    </div>

                    <Button
                        type="submit"
                        className="w-fit"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                </form>
            </SectionCard>

            <SectionCard
                title="Toko online"
                description="Link ini tetap sama walaupun nama usaha diganti, jadi link yang sudah dibagikan tetap jalan."
                icon={Globe}
                action={
                    business.open ? (
                        <LabelBadge
                            icon={CircleCheck}
                            label="Terima pesanan"
                            tone="success"
                        />
                    ) : (
                        <LabelBadge icon={CirclePause} label="Tutup" />
                    )
                }
            >
                <div className="flex flex-col gap-3">
                    <p className="text-sm break-all">{business.shopUrl}</p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={async () => {
                                if (!(await copy(business.shopUrl))) {
                                    toast.error(
                                        'Link tidak bisa disalin. Salin manual dari teks di atas.',
                                    );
                                }
                            }}
                        >
                            {copied === business.shopUrl ? (
                                <Check aria-hidden />
                            ) : (
                                <Copy aria-hidden />
                            )}
                            {copied === business.shopUrl
                                ? 'Tersalin'
                                : 'Salin link'}
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={business.shopUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink aria-hidden />
                                Buka toko
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={orders()}>
                                <Settings2 aria-hidden />
                                Atur toko di Pesanan
                            </Link>
                        </Button>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Buka atau tutup toko, nomor WA penjual, QRIS, dan bayar
                        online diatur di halaman Pesanan.
                    </p>
                </div>
            </SectionCard>
        </>
    );
}

BusinessSettings.layout = {
    breadcrumbs: [
        { title: 'Pengaturan', href: settings() },
        { title: 'Usaha', href: edit() },
    ],
};
