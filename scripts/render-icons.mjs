// Renders the icons in public/ from resources/icons/*.svg with Playwright's Chromium.
// Run: vendor/bin/sail node scripts/render-icons.mjs
import { chromium } from 'playwright';
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const mark = readFileSync('resources/icons/mark.svg', 'utf8');
const tile = readFileSync('resources/icons/app-icon.svg', 'utf8');

const browser = await chromium.launch();
const page = await browser.newPage();

async function render(svg, size, transparent) {
    await page.setViewportSize({ width: size, height: size });
    await page.setContent(
        `<html><body style="margin:0;background:transparent">${svg.replace('<svg ', `<svg width="${size}" height="${size}" `)}</body></html>`,
    );

    return page.screenshot({
        omitBackground: transparent,
        clip: { x: 0, y: 0, width: size, height: size },
    });
}

/** An .ico holding PNG images (supported by every current browser). */
function ico(images) {
    const header = Buffer.alloc(6 + 16 * images.length);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(images.length, 4);
    let offset = header.length;

    images.forEach(({ size, png }, i) => {
        const entry = 6 + 16 * i;
        header.writeUInt8(size >= 256 ? 0 : size, entry);
        header.writeUInt8(size >= 256 ? 0 : size, entry + 1);
        header.writeUInt16LE(1, entry + 4);
        header.writeUInt16LE(32, entry + 6);
        header.writeUInt32LE(png.length, entry + 8);
        header.writeUInt32LE(offset, entry + 12);
        offset += png.length;
    });

    return Buffer.concat([header, ...images.map((image) => image.png)]);
}

const favicons = [];
for (const size of [16, 32, 48]) {
    favicons.push({ size, png: await render(mark, size, true) });
}

writeFileSync('public/favicon.ico', ico(favicons));
copyFileSync('resources/icons/mark.svg', 'public/favicon.svg');
writeFileSync('public/apple-touch-icon.png', await render(tile, 180, false));
writeFileSync('public/icon-192.png', await render(tile, 192, false));
writeFileSync('public/icon-512.png', await render(tile, 512, false));
mkdirSync('resources/icons/build', { recursive: true });
writeFileSync(
    'resources/icons/build/discord-avatar.png',
    await render(tile, 1024, false),
);

await browser.close();
console.log(
    'Icons written to public/ and resources/icons/build/discord-avatar.png',
);
