import { describe, expect, it } from 'vite-plus/test';
import { chatParts } from './chat-text';

describe('chatParts', () => {
    it('reads bold and italic phrases and keeps the rest as text', () => {
        expect(chatParts('Laba bersih *Rp1.871.280*.\n\n• _naik_ 5%')).toEqual([
            { kind: 'text', text: 'Laba bersih ' },
            { kind: 'bold', text: 'Rp1.871.280' },
            { kind: 'text', text: '.\n\n• ' },
            { kind: 'italic', text: 'naik' },
            { kind: 'text', text: ' 5%' },
        ]);
    });

    it('leaves marks inside words and lone stars alone', () => {
        expect(chatParts('t.me/lais_bot_x dan 2*3')).toEqual([
            { kind: 'text', text: 't.me/lais_bot_x dan 2*3' },
        ]);
    });
});
