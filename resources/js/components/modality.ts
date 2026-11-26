export const MODALITIES = [
    { key: 'common', letter: 'C', colour: '#1F7A4D' },
    { key: 'adaptive', letter: 'A', colour: '#1E5FBF' },
    { key: 'specialized', letter: 'S', colour: '#B4540A' },
    { key: 'distinct', letter: 'D', colour: '#6B3FB5' },
] as const;

export const NO_MODALITY = 'none';

export const colourOf = (letter: string): string => MODALITIES.find((modality) => modality.letter === letter)?.colour ?? '#454C57';
