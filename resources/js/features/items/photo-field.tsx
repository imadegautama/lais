import { ImagePicker } from '@/components/app/image-picker';
import { SectionCard } from '@/components/app/section-card';

/** The photo customers see on the shop page (UI-PAGES C6). */
export function PhotoField(props: {
    /** The saved photo, unless the owner just removed it. */
    current: string | null;
    file: File | null;
    error?: string;
    onFile: (file: File) => void;
    onRemove: () => void;
}) {
    return (
        <SectionCard
            title="Foto di toko"
            description="Boleh dikosongkan. Tanpa foto, toko menampilkan huruf depan namanya."
        >
            <ImagePicker {...props} maxSide={800} alt="Pratinjau foto produk" />
        </SectionCard>
    );
}
