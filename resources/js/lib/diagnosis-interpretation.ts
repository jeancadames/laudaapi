export interface InterpretationBand {
    min: number;
    max: number;
    label: string;
}

export interface InterpretationBands {
    maturity: InterpretationBand[];
    capacity: InterpretationBand[];
    urgency: InterpretationBand[];
}

/** Ranges are supplied by Laravel's official methodology configuration. */
export function interpretationBand(value: number, bands: InterpretationBand[]) {
    return bands.find((item) => value >= item.min && value <= item.max);
}
