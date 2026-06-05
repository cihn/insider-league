/**
 * Minimal base class for the component pattern: every component receives its
 * props on construction and returns a DOM node from render().
 */
export class Component {
    constructor(props = {}) {
        this.props = props;
    }

    /**
     * @returns {Node}
     */
    render() {
        throw new Error('Component.render() must be implemented by the subclass.');
    }
}
