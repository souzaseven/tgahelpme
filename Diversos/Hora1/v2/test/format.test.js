import { describe, it, expect } from 'vitest';
import {
    pad2,
    cap,
    greetingKey,
    dayFraction,
    wallClock,
    gmtLabel,
    weatherGlyph,
} from '../assets/js/format.js';

describe('pad2', () => {
    it('preenche com zero à esquerda', () => {
        expect(pad2(3)).toBe('03');
        expect(pad2(42)).toBe('42');
        expect(pad2(0)).toBe('00');
    });
});

describe('cap', () => {
    it('capitaliza a primeira letra e preserva o resto', () => {
        expect(cap('sábado')).toBe('Sábado');
        expect(cap('céu limpo')).toBe('Céu limpo');
    });
    it('lida com string vazia', () => {
        expect(cap('')).toBe('');
    });
});

describe('greetingKey', () => {
    it.each([
        [0, 'dawn'],
        [4, 'dawn'],
        [5, 'morning'],
        [11, 'morning'],
        [12, 'afternoon'],
        [17, 'afternoon'],
        [18, 'evening'],
        [23, 'evening'],
    ])('hora %i → %s', (hour, key) => {
        expect(greetingKey(hour)).toBe(key);
    });
});

describe('dayFraction', () => {
    it('meia-noite = 0', () => {
        expect(dayFraction(new Date(2026, 0, 1, 0, 0, 0))).toBe(0);
    });
    it('meio-dia ≈ 0,5', () => {
        expect(dayFraction(new Date(2026, 0, 1, 12, 0, 0))).toBeCloseTo(0.5, 5);
    });
    it('23:59:59 ≈ 1', () => {
        expect(dayFraction(new Date(2026, 0, 1, 23, 59, 59))).toBeCloseTo(1, 3);
    });
});

describe('wallClock', () => {
    it('aplica o deslocamento ao instante UTC', () => {
        const noonUtc = Date.UTC(2026, 0, 1, 12, 0, 0) / 1000;
        expect(wallClock(noonUtc, -3 * 3600)).toBe('09:00');
        expect(wallClock(noonUtc, 5.5 * 3600)).toBe('17:30');
    });
});

describe('gmtLabel', () => {
    it('formata o deslocamento do fuso', () => {
        expect(gmtLabel({ getTimezoneOffset: () => 180 })).toBe('GMT-03:00');
        expect(gmtLabel({ getTimezoneOffset: () => -60 })).toBe('GMT+01:00');
        expect(gmtLabel({ getTimezoneOffset: () => 0 })).toBe('GMT+00:00');
    });
});

describe('weatherGlyph', () => {
    it('mapeia os códigos do OpenWeather', () => {
        expect(weatherGlyph('01d')).toBe('☀');
        expect(weatherGlyph('01n')).toBe('☾');
        expect(weatherGlyph('03d')).toBe('☁');
        expect(weatherGlyph('10n')).toBe('☂');
        expect(weatherGlyph('11d')).toBe('☇');
        expect(weatherGlyph('13d')).toBe('❄');
    });
    it('tem fallback para código desconhecido', () => {
        expect(weatherGlyph('')).toBe('·');
        expect(weatherGlyph('99x')).toBe('·');
    });
});
