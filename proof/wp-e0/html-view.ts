import type { Block } from './layout';
import { absolutePosition } from './layout';

export function renderHtmlView(container: HTMLElement, blocks: Block[], artboard: { width: number; height: number }) {
    container.textContent = '';
    const board = document.createElement('div');
    board.id = 'html-artboard';
    board.className = 'html-artboard';
    board.style.width = `${artboard.width}px`;
    board.style.height = `${artboard.height}px`;
    container.append(board);
    for (const block of blocks) {
        const { x, y } = absolutePosition(blocks, block.id);
        const box = document.createElement('div');
        box.className = 'html-block';
        box.dataset.id = block.id;
        box.style.left = `${x}px`;
        box.style.top = `${y}px`;
        box.style.width = `${block.width}px`;
        box.style.height = `${block.height}px`;
        box.style.backgroundColor = block.fill;
        block.lines.forEach((line, index) => {
            const row = document.createElement('div');
            row.className = 'html-line';
            row.dataset.line = String(index);
            row.textContent = line;
            row.style.left = `${block.padding}px`;
            row.style.top = `${block.padding + index * block.lineHeight}px`;
            row.style.height = `${block.lineHeight}px`;
            row.style.lineHeight = `${block.lineHeight}px`;
            row.style.font = `${block.font.weight} ${block.font.size}px/${block.lineHeight}px "${block.font.family}"`;
            row.style.color = block.textColor;
            box.append(row);
        });
        board.append(box);
    }
}
