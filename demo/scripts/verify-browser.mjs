import { execSync } from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer-core';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const demoRoot = path.resolve(__dirname, '..');
const distDir = path.join(demoRoot, 'dist');
const browserCheckDir = path.join(demoRoot, '.browser-check');
const screenshotsDir = path.join(browserCheckDir, 'screenshots');
const mountPrefix = '/consultant-console-demo';

const MIME_TYPES = {
    '.html': 'text/html; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.pdf': 'application/pdf',
    '.json': 'application/json; charset=utf-8',
    '.ico': 'image/x-icon',
    '.woff2': 'font/woff2',
};

function assert(condition, message) {
    if (!condition) {
        throw new Error(`Assertion failed: ${message}`);
    }
}

function findExecutableInDir(dir, names, maxDepth = 4) {
    if (!fs.existsSync(dir) || maxDepth < 0) {
        return null;
    }
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isFile() && names.includes(entry.name)) {
            return fullPath;
        }
        if (entry.isDirectory()) {
            const nested = findExecutableInDir(fullPath, names, maxDepth - 1);
            if (nested) {
                return nested;
            }
        }
    }
    return null;
}

function resolveChromeBinary() {
    if (process.env.CHROME_PATH && fs.existsSync(process.env.CHROME_PATH)) {
        return process.env.CHROME_PATH;
    }

    const localHeadless = findExecutableInDir(browserCheckDir, [
        'chrome-headless-shell',
        'chrome',
    ]);
    if (localHeadless) {
        return localHeadless;
    }

    const candidates = [
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ];
    for (const candidate of candidates) {
        if (fs.existsSync(candidate)) {
            return candidate;
        }
    }

    throw new Error(
        [
            'Could not find Chrome/Chromium binary.',
            'Set CHROME_PATH or install headless Chrome with:',
            '  cd demo && npx @puppeteer/browsers install chrome-headless-shell@stable --path .browser-check',
        ].join('\n'),
    );
}

function startStaticServer() {
    return new Promise((resolve, reject) => {
        const server = http.createServer((req, res) => {
            const reqUrl = new URL(req.url || '/', 'http://127.0.0.1');
            let pathname = decodeURIComponent(reqUrl.pathname);

            if (pathname === mountPrefix) {
                res.writeHead(302, { Location: `${mountPrefix}/` });
                res.end();
                return;
            }

            if (!pathname.startsWith(`${mountPrefix}/`)) {
                res.writeHead(404);
                res.end('Not found');
                return;
            }

            const relPath =
                pathname.slice(`${mountPrefix}/`.length) || 'index.html';
            const filePath = path.resolve(distDir, relPath);

            if (!filePath.startsWith(distDir) || !fs.existsSync(filePath)) {
                res.writeHead(404);
                res.end('Not found');
                return;
            }

            const stat = fs.statSync(filePath);
            const finalPath = stat.isDirectory()
                ? path.join(filePath, 'index.html')
                : filePath;
            const ext = path.extname(finalPath).toLowerCase();
            res.writeHead(200, {
                'Content-Type':
                    MIME_TYPES[ext] || 'application/octet-stream',
            });
            fs.createReadStream(finalPath).pipe(res);
        });

        server.on('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const addr = server.address();
            resolve({
                server,
                baseUrl: `http://127.0.0.1:${addr.port}${mountPrefix}/`,
            });
        });
    });
}

async function getText(page, selector) {
    await page.waitForSelector(selector, { visible: true, timeout: 10000 });
    return page.$eval(selector, (el) => el.textContent?.trim() || '');
}

async function main() {
    console.log('1. Building static demo bundle...');
    execSync('npm run build', { cwd: demoRoot, stdio: 'inherit' });

    fs.mkdirSync(screenshotsDir, { recursive: true });

    const executablePath = resolveChromeBinary();
    console.log(`2. Launching browser: ${executablePath}`);

    const { server, baseUrl } = await startStaticServer();
    console.log(`3. Serving demo/dist at ${baseUrl}`);

    const browser = await puppeteer.launch({
        executablePath,
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    const pageErrors = [];
    const consoleErrors = [];

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1280, height: 900 });

        page.on('pageerror', (err) => pageErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        // Step 1: Initial load starts directly on MRR discrepancy in Workshop
        await page.goto(baseUrl, { waitUntil: 'networkidle0' });
        const bannerText = await getText(page, '[data-test="mrr-discrepancy-banner"]');
        assert(
            bannerText.includes('Unit Economics Board Baseline') &&
                bannerText.includes('Official MRR Snapshot Timing'),
            `Expected MRR discrepancy banner on initial load, got: ${bannerText}`,
        );
        await page.screenshot({
            path: path.join(screenshotsDir, '01-workshop-initial.png'),
            fullPage: true,
        });
        console.log('   ✓ Initial load opens directly on MRR discrepancy in Workshop');

        // Flow A: Choose RevOps Rolling daily -> Workbench -> Approve -> Client Handbook (Sample PDF)
        await page.click('[data-test="mrr-choice-rolling_daily"]');
        const draftSnapshot = await getText(
            page,
            '[data-test="workshop-selected-snapshot"]',
        );
        assert(
            draftSnapshot.includes('Rolling daily snapshot'),
            `Expected Rolling daily snapshot in draft, got: ${draftSnapshot}`,
        );

        await page.click('[data-test="continue-to-workbench-btn"]');
        const readinessBefore = await getText(
            page,
            '[data-test="workbench-readiness-count"]',
        );
        assert(
            readinessBefore.includes('2/3 Ready'),
            `Expected 2/3 Ready before approval, got: ${readinessBefore}`,
        );
        await page.screenshot({
            path: path.join(screenshotsDir, '02-workbench-rolling-pending.png'),
            fullPage: true,
        });

        await page.click('[data-test="approve-mrr-btn"]');
        const readinessAfter = await getText(
            page,
            '[data-test="workbench-readiness-count"]',
        );
        assert(
            readinessAfter.includes('3/3 Ready'),
            `Expected 3/3 Ready after approval, got: ${readinessAfter}`,
        );
        await page.waitForSelector('[data-test="mrr-approved-banner"]', {
            visible: true,
        });

        await page.click('[data-test="workbench-next-handbook-btn"]');
        const approvedRollingChoice = await getText(
            page,
            '[data-test="handbook-approved-mrr-choice"]',
        );
        assert(
            approvedRollingChoice.includes('Rolling daily snapshot'),
            `Expected Client Handbook to show Rolling daily snapshot, got: ${approvedRollingChoice}`,
        );

        const sampleBadge = await getText(page, '[data-test="pdf-status-badge"]');
        const sampleBtn = await getText(page, '[data-test="pdf-download-link"]');
        assert(
            sampleBadge.includes('Sample PDF') &&
                sampleBtn.includes('Download Sample PDF'),
            `Expected Sample PDF labeling for rolling daily choice, got badge="${sampleBadge}", btn="${sampleBtn}"`,
        );

        // Verify bundled PDF is actually served from the subdirectory
        const pdfHref = await page.$eval(
            '[data-test="pdf-download-link"]',
            (a) => a.href,
        );
        const pdfRes = await fetch(pdfHref);
        assert(
            pdfRes.ok &&
                (pdfRes.headers.get('content-type') || '').includes(
                    'application/pdf',
                ),
            `Expected valid PDF at ${pdfHref}, got status ${pdfRes.status}`,
        );

        await page.screenshot({
            path: path.join(screenshotsDir, '03-handbook-rolling-sample-pdf.png'),
            fullPage: true,
        });
        console.log(
            '   ✓ Flow A (Rolling daily) approved, displayed in Client Handbook, and PDF labeled Sample PDF',
        );

        // Persistence across reload
        await page.reload({ waitUntil: 'networkidle0' });
        const persistedChoice = await getText(
            page,
            '[data-test="handbook-approved-mrr-choice"]',
        );
        assert(
            persistedChoice.includes('Rolling daily snapshot'),
            `Expected state to persist across reload, got: ${persistedChoice}`,
        );
        console.log('   ✓ State persists across page reload');

        // Start over
        await page.click('[data-test="start-over-btn"]');
        const resetSnapshot = await getText(
            page,
            '[data-test="workshop-selected-snapshot"]',
        );
        assert(
            resetSnapshot.includes('Not selected yet'),
            `Expected Start over to reset MRR choice, got: ${resetSnapshot}`,
        );
        console.log('   ✓ Start over clears decision and returns to Workshop');

        // Flow B: Choose Finance Month-end close -> Workbench -> Approve -> Client Handbook (Matched PDF)
        await page.click('[data-test="mrr-choice-last_calendar_day"]');
        await page.click('[data-test="continue-to-workbench-btn"]');
        await page.click('[data-test="approve-mrr-btn"]');
        await page.click('[data-test="workbench-next-handbook-btn"]');

        const approvedMonthEndChoice = await getText(
            page,
            '[data-test="handbook-approved-mrr-choice"]',
        );
        assert(
            approvedMonthEndChoice.includes(
                'Last calendar day (month-end close)',
            ),
            `Expected Month-end close in Client Handbook, got: ${approvedMonthEndChoice}`,
        );

        const matchedBadge = await getText(page, '[data-test="pdf-status-badge"]');
        const matchedBtn = await getText(page, '[data-test="pdf-download-link"]');
        assert(
            matchedBadge.includes('Matches approved baseline') &&
                matchedBtn.includes('Download Handbook PDF'),
            `Expected matched PDF label for month-end close, got badge="${matchedBadge}", btn="${matchedBtn}"`,
        );

        await page.screenshot({
            path: path.join(
                screenshotsDir,
                '04-handbook-month-end-matched-pdf.png',
            ),
            fullPage: true,
        });
        console.log(
            '   ✓ Flow B (Month-end close) approved, displayed in Client Handbook, and PDF matched',
        );

        // Mobile viewport check
        await page.setViewport({ width: 390, height: 844 });
        await new Promise((r) => setTimeout(r, 150));
        const hasOverflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                window.innerWidth + 1,
        );
        assert(!hasOverflow, 'Mobile viewport has horizontal overflow');
        await page.screenshot({
            path: path.join(screenshotsDir, '05-handbook-mobile.png'),
            fullPage: true,
        });
        console.log('   ✓ Mobile layout (390px) verified without horizontal overflow');

        assert(
            pageErrors.length === 0,
            `Page errors encountered: ${pageErrors.join('; ')}`,
        );
        assert(
            consoleErrors.length === 0,
            `Console errors encountered: ${consoleErrors.join('; ')}`,
        );
        console.log('All browser checks passed!');
    } finally {
        await browser.close();
        server.close();
    }
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
