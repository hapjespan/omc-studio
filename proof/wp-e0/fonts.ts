import { fontManager } from '@open-pencil/core/text';
import interRegular from '@open-pencil/core/assets/Inter-Regular.ttf?url';
import interSemiBold from '@open-pencil/core/assets/Inter-SemiBold.ttf?url';
import interBold from '@open-pencil/core/assets/Inter-Bold.ttf?url';
import groteskRegular from '@expo-google-fonts/space-grotesk/400Regular/SpaceGrotesk_400Regular.ttf?url';
import groteskBold from '@expo-google-fonts/space-grotesk/700Bold/SpaceGrotesk_700Bold.ttf?url';

interface Face {
    family: string;
    style: string;
    weight: number;
    url: string;
}

export const FACES: Face[] = [
    { family: 'Inter', style: 'Regular', weight: 400, url: interRegular },
    { family: 'Inter', style: 'SemiBold', weight: 600, url: interSemiBold },
    { family: 'Inter', style: 'Bold', weight: 700, url: interBold },
    { family: 'Space Grotesk', style: 'Regular', weight: 400, url: groteskRegular },
    { family: 'Space Grotesk', style: 'Bold', weight: 700, url: groteskBold },
];

export async function loadFace(family: string, style: string): Promise<ArrayBuffer | null> {
    const face = FACES.find((candidate) => candidate.family === family && candidate.style === style);
    return face ? (await fetch(face.url)).arrayBuffer() : null;
}

export function lockFontSources() {
    fontManager.setOnlineFontProviders({ google: false, fontsource: false, bunny: false, fontshare: false });
    fontManager.setWebFontFetch(null);
    fontManager.setBundledFontLocator((file) => FACES.find((face) => face.url.includes(file.replace(/\.ttf$/, '')))?.url ?? '');
}

export async function registerFaces() {
    for (const face of FACES) {
        const data = await loadFace(face.family, face.style);
        if (data) fontManager.markLoaded(face.family, face.style, data);
    }
}

export async function loadBrowserFonts() {
    for (const face of FACES) {
        const font = new FontFace(face.family, `url(${face.url})`, { weight: String(face.weight) });
        document.fonts.add(await font.load());
    }
}
