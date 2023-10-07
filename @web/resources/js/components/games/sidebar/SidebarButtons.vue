<template>
  <div :class="['game-sidebar-buttons-container'].concat(data.classList ? data.classList : [])">
    <div v-for="(button, i) in data.buttons" :key="i"
         :class="'game-sidebar-buttons-container-button ' + (button.preventMark ? '' : ((button.first === undefined && i === 0 && active === -1) || active === i ? 'active' : (button.first ? 'active' : ''))) + ' ' + (button.class ? button.class : '')"
         @click="button.preventMark ? false : (gameInstance.playTimeout || gameInstance.game.extendedState === 'in-progress' ? false : active = i); (gameInstance.playTimeout || gameInstance.game.extendedState === 'in-progress') && !button.isAction ? false : onClick(button, $event)">
      <span v-if="!button.hideLabel" v-html="button.label"></span>
      <input v-if="button.isInput"
             :disabled="gameInstance.playTimeout || gameInstance.game.extendedState === 'in-progress'" type="number"
             :class="inputError ? 'error' : ''" :placeholder="$t('general.mines')" v-model="inputValue[i]"
             @input="onInput(button, i)">
    </div>
  </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    props: {
      data: {
        type: Object
      }
    },
    computed: {
      ...mapGetters(['gameInstance'])
    },
    watch: {
      // gameInstance() {
        // console.log('crvena:' + this.gameInstance.game.data['wheel-double-red']);
        // console.log('crvena:' + this.gameInstance.game.data['wheel-double-red'].toFixed(4));
        // if(this.gameInstance && this.gameInstance.game && this.gameInstance.game.data
        //  && Object.prototype.hasOwnProperty.call(this.gameInstance.game.data, 'wheel-double-red')) {
        //   $('.wheel-double .wheel-button-red span').text('x' + Number(this.gameInstance.game.data['wheel-double-red']).toFixed(4));
        //   $('.wheel-double .wheel-button-red span').text('x' + Number(this.gameInstance.game.data['wheel-double-black']).toFixed(4));
        //   $('.wheel-double .wheel-button-red span').text('x' + Number(this.gameInstance.game.data['wheel-double-green']).toFixed(4));
        //   $('.wheel-x50 .wheel-button-red span').text('x' + Number(this.gameInstance.game.data['wheel-x50-red']).toFixed(4));
        //   $('.wheel-x50 .wheel-button-black span').text('x' + Number(this.gameInstance.game.data['wheel-x50-black']).toFixed(4)); 
        //   $('.wheel-x50 .wheel-button-green span').text('x' + Number(this.gameInstance.game.data['wheel-x50-green']).toFixed(4)); 
        //   $('.wheel-x50 .wheel-button-yellow span').text('x' + Number(this.gameInstance.game.data['wheel-x50-yellow']).toFixed(4));
        // }
      // }
    },
    data() {
      return {
        active: -1,
        inputValue: _.fill(new Array(this.data.buttons.length), ''),
        inputError: false
      }
    },
    methods: {
      onInput(button, i) {
        const val = parseInt(this.inputValue[i]);
        if (isNaN(val) || val < button.input.min || val > button.input.max) this.inputError = true;
        else {
          this.inputError = false;
          button.callback(val);
        }
      },
      onClick(button, target) {
        if ($(target.target).is('span')) target = target.parent;
        else target = target.target;

        if ($(target).hasClass('disabled')) return;

        if (button.type === 'input') {
          if (button.isInput) {
            const val = parseInt(this.inputValue);
            if (!(isNaN(val) || val < button.input.min || val > button.input.max)) button.callback(val);
          }

          button.hideLabel = true;
          button.isInput = true;
        } else button.callback();
      }
    }
  }
</script>

<style lang="scss" scoped>
  .game-sidebar-buttons-container-button.disabled {
    pointer-events: none;
  }
</style>
