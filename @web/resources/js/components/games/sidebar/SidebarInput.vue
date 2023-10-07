<template>
    <!-- <input type="text" @input="onInput($event.target.value)" :value="value" :disabled="gameInstance.game && gameInstance.game.extendedState === 'in-progress'"> -->
    <input type="text" v-model.lazy="value" :disabled="gameInstance.game && gameInstance.game.extendedState === 'in-progress'">
</template>

<script>
    import { mapGetters } from 'vuex';

    export default {
        data() {
            return {
              //  value: ''
                value: this.data.value
            }
        },
        watch: {
            value(newValue, oldValue) {
                if(this.data.callback) {
                    if(this.data.callback(newValue)) this.value = newValue;
                    else this.value = oldValue;
                }
                else this.value = newValue;

                const instance = this.gameInstance;
                instance.autoCashout = this.value;
                this.$store.dispatch('setGameInstance', instance);
            }
        },
        computed: {
            ...mapGetters(['gameInstance'])
        },
        mounted() {
            //if(this.data.value) this.value = this.data.value;
            if(this.data.value) {
                this.value = this.data.value;
                const instance = this.gameInstance;
                instance.autoCashout = this.value;
                this.$store.dispatch('setGameInstance', instance);
            }
        },
        methods: {
            // onInput(value) {
            //     const oldValue = this.value;
            //     if(this.data.callback) {
            //         if(this.data.callback(value)) this.value = value;
            //         else this.value = oldValue;
            //     }
            //     else this.value = value;
            // }
        },
        props: ['data']
    }
</script>
