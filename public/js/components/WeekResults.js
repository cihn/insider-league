import { Component } from '../lib/Component.js';
import { el } from '../lib/dom.js';

/**
 * Results for a single week, with previous/next navigation and inline editing
 * of played scores (the "edit results" extra). Editing a score calls
 * onEdit(matchId, homeGoals, awayGoals); the parent then re-renders.
 */
export class WeekResults extends Component {
    render() {
        const { week, totalWeeks, matches, onChangeWeek } = this.props;

        const head = el('div', { class: 'card-header bg-dark text-white d-flex justify-content-between align-items-center' },
            el('button', {
                class: 'btn btn-sm btn-outline-light py-0',
                disabled: week <= 1,
                onClick: () => onChangeWeek(week - 1),
            }, '‹'),
            el('span', { class: 'fw-semibold' }, `Week ${week}`),
            el('button', {
                class: 'btn btn-sm btn-outline-light py-0',
                disabled: week >= totalWeeks,
                onClick: () => onChangeWeek(week + 1),
            }, '›'),
        );

        const list = el('ul', { class: 'list-group list-group-flush' },
            ...matches.map((match) => this.renderRow(match)),
        );

        return el('div', { class: 'card shadow-sm' }, head, list);
    }

    renderRow(match) {
        const row = el('li', { class: 'list-group-item' });
        this.fillDisplay(row, match);
        return row;
    }

    fillDisplay(row, match) {
        const { onEdit } = this.props;

        const score = match.played
            ? el('span', { class: 'fw-bold text-center', style: 'min-width:52px' }, `${match.home_goals} - ${match.away_goals}`)
            : el('span', { class: 'text-muted text-center', style: 'min-width:52px' }, '-');

        const edit = match.played
            ? el('button', {
                class: 'btn btn-sm btn-link text-secondary p-0 ms-2',
                title: 'Edit result',
                onClick: () => this.fillEditor(row, match, onEdit),
            }, '✎')
            : null;

        row.replaceChildren(
            el('div', { class: 'd-flex align-items-center gap-2' },
                el('span', { class: 'flex-fill text-end' }, match.home_team),
                score,
                el('span', { class: 'flex-fill text-start' }, match.away_team),
                edit,
            ),
        );
    }

    fillEditor(row, match, onEdit) {
        const homeInput = el('input', {
            type: 'number', min: '0', max: '99', value: match.home_goals,
            class: 'form-control form-control-sm text-center fw-bold', style: 'width:52px',
        });
        const awayInput = el('input', {
            type: 'number', min: '0', max: '99', value: match.away_goals,
            class: 'form-control form-control-sm text-center fw-bold', style: 'width:52px',
        });

        const save = () => onEdit(match.id, clampGoals(homeInput.value), clampGoals(awayInput.value));

        row.replaceChildren(
            el('div', { class: 'd-flex align-items-center gap-2' },
                el('span', { class: 'flex-fill text-end small text-truncate' }, match.home_team),
                el('span', { class: 'd-flex align-items-center gap-1' }, homeInput, '-', awayInput),
                el('span', { class: 'flex-fill text-start small text-truncate' }, match.away_team),
            ),
            el('div', { class: 'd-flex justify-content-center gap-2 mt-2' },
                el('button', { class: 'btn btn-sm btn-success py-0', onClick: save }, 'Save'),
                el('button', {
                    class: 'btn btn-sm btn-outline-secondary py-0',
                    onClick: () => this.fillDisplay(row, match),
                }, 'Cancel'),
            ),
        );

        homeInput.focus();
    }
}

function clampGoals(value) {
    const number = Math.trunc(Number(value));
    if (Number.isNaN(number) || number < 0) {
        return 0;
    }
    return Math.min(number, 99);
}
