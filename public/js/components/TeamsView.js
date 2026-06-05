import { Component } from '../lib/Component.js';
import { el } from '../lib/dom.js';

/**
 * Step 1: list the tournament teams and let the user generate fixtures.
 */
export class TeamsView extends Component {
    render() {
        const { teams, onGenerate } = this.props;

        const rows = teams.map((team) => el('tr', {},
            el('td', { class: 'fw-medium' }, team.name),
            el('td', { class: 'text-center' }, team.strength),
        ));

        return el('section', {},
            el('h1', { class: 'h3 text-center fw-light text-secondary mb-4' }, 'Tournament Teams'),
            el('div', { class: 'card shadow-sm mx-auto', style: 'max-width:640px' },
                el('table', { class: 'table table-hover align-middle mb-0' },
                    el('thead', { class: 'table-dark' },
                        el('tr', {},
                            el('th', {}, 'Team Name'),
                            el('th', { class: 'text-center', style: 'width:120px' }, 'Strength'),
                        ),
                    ),
                    el('tbody', {}, ...rows),
                ),
            ),
            el('div', { class: 'text-center mt-4' },
                el('button', { class: 'btn btn-primary btn-lg', onClick: onGenerate }, 'Generate Fixtures'),
            ),
        );
    }
}
