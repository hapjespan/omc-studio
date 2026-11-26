import { fontManager } from '@open-pencil/core/text';
import interRegular from '@open-pencil/core/assets/Inter-Regular.ttf?url';
import interMedium from '@open-pencil/core/assets/Inter-Medium.ttf?url';
import interSemiBold from '@open-pencil/core/assets/Inter-SemiBold.ttf?url';
import interBold from '@open-pencil/core/assets/Inter-Bold.ttf?url';
import groteskRegular from '@expo-google-fonts/space-grotesk/400Regular/SpaceGrotesk_400Regular.ttf?url';
import groteskMedium from '@expo-google-fonts/space-grotesk/500Medium/SpaceGrotesk_500Medium.ttf?url';
import groteskSemiBold from '@expo-google-fonts/space-grotesk/600SemiBold/SpaceGrotesk_600SemiBold.ttf?url';
import groteskBold from '@expo-google-fonts/space-grotesk/700Bold/SpaceGrotesk_700Bold.ttf?url';

interface Face {
    family: string;
    style: string;
    weight: number;
    url: string;
}

const FACES: Face[] = [
    { family: 'Inter', style: 'Regular', weight: 400, url: interRegular },
    { family: 'Inter', style: 'Medium', weight: 500, url: interMedium },
    { family: 'Inter', style: 'SemiBold', weight: 600, url: interSemiBold },
    { family: 'Inter', style: 'Bold', weight: 700, url: interBold },
    { family: 'Space Grotesk', style: 'Regular', weight: 400, url: groteskRegular },
    { family: 'Space Grotesk', style: 'Medium', weight: 500, url: groteskMedium },
    { family: 'Space Grotesk', style: 'SemiBold', weight: 600, url: groteskSemiBold },
    { family: 'Space Grotesk', style: 'Bold', weight: 700, url: groteskBold },
];

export async function loadFace(family: string, style: string): Promise<ArrayBuffer | null> {
    const face = FACES.find((candidate) => candidate.family === family && candidate.style === style);
    return face ? (await fetch(face.url)).arrayBuffer() : null;
}

export async function registerFonts(): Promise<void> {
    fontManager.setBundledFontLocator((file) => FACES.find((face) => face.url.includes(file.replace(/\.ttf$/, '')))?.url ?? '');
    await Promise.all(
        FACES.map(async (face) => {
            const data = await loadFace(face.family, face.style);
            if (data) fontManager.markLoaded(face.family, face.style, data);
        }),
    );
}
