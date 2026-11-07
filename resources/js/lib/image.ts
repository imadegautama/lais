/** Longest side of a receipt photo sent to the assistant; text stays readable. */
const MAX_SIDE = 1600;

/** The size that keeps the aspect ratio with neither side above `max`. */
export function fitWithin(
    width: number,
    height: number,
    max: number,
): { width: number; height: number } {
    const scale = Math.min(1, max / Math.max(width, height));

    return {
        width: Math.round(width * scale),
        height: Math.round(height * scale),
    };
}

/**
 * A phone photo (often 3 to 8 MB) as a JPEG data URI of a few hundred KB,
 * so it fits AssistantRequest's 5 MB limit and travels in the JSON body.
 */
export async function photoToDataUrl(file: File): Promise<string> {
    const bitmap = await createImageBitmap(file);
    const { width, height } = fitWithin(bitmap.width, bitmap.height, MAX_SIDE);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d')?.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();

    return canvas.toDataURL('image/jpeg', 0.8);
}

/**
 * A product photo as an 800 px JPEG file of about 100 KB, so the shop page
 * stays light on a phone connection. Formats the browser cannot draw
 * (HEIC on some phones) go up as they are; the server checks the type.
 */
export async function shrinkPhoto(file: File, max = 800): Promise<File> {
    try {
        const bitmap = await createImageBitmap(file);
        const { width, height } = fitWithin(bitmap.width, bitmap.height, max);
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d')?.drawImage(bitmap, 0, 0, width, height);
        bitmap.close();
        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', 0.82),
        );

        return blob
            ? new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {
                  type: 'image/jpeg',
              })
            : file;
    } catch {
        return file;
    }
}

/** A photo kept as a data URI (the assistant's receipt) as a file a form can upload. */
export async function dataUrlToFile(
    dataUrl: string,
    name: string,
): Promise<File> {
    const blob = await (await fetch(dataUrl)).blob();

    return new File([blob], name, { type: blob.type });
}
