export type ChatPart = { kind: 'text' | 'bold' | 'italic'; text: string };

/**
 * The chat's own marks, as WhatsApp and the bot write them: *bold* and
 * _italic_. Only a whole phrase counts, so lais_bot or 2*3 stay as typed.
 * Everything else is plain text; no HTML is ever read from a reply.
 */
export function chatParts(text: string): ChatPart[] {
    return text
        .split(/(\*[^*\n]+\*|(?<![\p{L}\p{N}_])_[^_\n]+_(?![\p{L}\p{N}_]))/u)
        .filter((part) => part !== '')
        .map((part) =>
            part.length > 2 && part.startsWith('*') && part.endsWith('*')
                ? { kind: 'bold', text: part.slice(1, -1) }
                : part.length > 2 && part.startsWith('_') && part.endsWith('_')
                  ? { kind: 'italic', text: part.slice(1, -1) }
                  : { kind: 'text', text: part },
        );
}
