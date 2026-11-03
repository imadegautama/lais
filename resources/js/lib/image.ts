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
