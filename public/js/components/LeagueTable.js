import { Component } from '../lib/Component.js';
import { el } from '../lib/dom.js';

/**
 * League standings table. Columns follow the Premier League layout
 * (Played, Won, Drawn, Lost, Goal Difference, Points).
 */
export class LeagueTable extends Component {
    render() {
        const { table } = this.props;

        const header = el('tr', {},
            el('th', { style: 'width:40px' }, '#'),
            el('th', {}, 'Team Name'),
            el('th', { class: 'text-center' }, 'P'),
            el('th', { class: 'text-center' }, 'W'),
            el('th', { class: 'text-center' }, 'D'),
            el('th', { class: 'text-center' }, 'L'),
            el('th', { class: 'text-center' }, 'GD'),
            el('th', { class: 'text-center' }, 'Pts'),
        );

        const rows = table.map((row, index) => el('tr', {},
            el('td', { class: 'text-muted' }, index + 1),
            el('td', { class: 'fw-medium' }, row.team_name),
            el('td', { class: 'text-center' }, row.played),
            el('td', { class: 'text-center' }, row.won),
            el('td', { class: 'text-center' }, row.drawn),
            el('td', { class: 'text-center' }, row.lost),
            el('td', { class: 'text-center' }, formatGoalDifference(row.goal_difference)),
            el('td', { class: 'text-center fw-bold' }, row.points),
        ));

        return el('div', { class: 'card shadow-sm' },
            el('table', { class: 'table table-hover align-middle mb-0' },
                el('thead', { class: 'table-dark' }, header),
                el('tbody', {}, ...rows),
            ),
        );
    }
}

function formatGoalDifference(value) {
    return value > 0 ? `+${value}` : String(value);
}
