/**
 * [Chart.PieceLabel.js]{@link https://github.com/emn178/Chart.PieceLabel.js}
 *
 * @version 0.4.0
 * @author Chen, Yi-Cyuan [emn178@gmail.com]
 * @copyright Chen, Yi-Cyuan 2017
 * @license MIT
 */
(function () {
  function PieceLabel() {
    this.drawDataset = this.drawDataset.bind(this);
  }

  PieceLabel.prototype.beforeDatasetsUpdate = function (chartInstance) {
    if (this.parseOptions(chartInstance) && this.position === 'outside') {
      var padding = this.fontSize * 1.5 + 2;
      chartInstance.chartArea.top += padding;
      chartInstance.chartArea.bottom -= padding;
    }
  };

  PieceLabel.prototype.afterDraw = function (chartInstance) {
    if (!this.parseOptions(chartInstance)) {
      return;
    }
    this.labelBounds = [];
    chartInstance.config.data.datasets.forEach(this.drawDataset);
    if (this.hasTooltip) {
      chartInstance.tooltip.draw();
    }
  };

  PieceLabel.prototype.drawDataset = function (dataset) {
    var ctx = this.ctx;
    var chartInstance = this.chartInstance;
    var meta = dataset._meta[Object.keys(dataset._meta)[0]];
    var totalPercentage = 0;
    for (var i = 0; i < meta.data.length; i++) {
      var element = meta.data[i],
        view = element._view;

      if (this.hasTooltip) {
        element.draw();
      }

      var text;
      switch (this.mode) {
        case 'value':
          var value = dataset.data[i];
          if (this.format) {
            value = this.format(value);
          }
          text = value.toString();
          break;
        case 'label':
          text = chartInstance.config.data.labels[i];
          break;
        case 'percentage':
        default:
          var percentage = view.circumference / this.options.circumference * 100;
          percentage = parseFloat(percentage.toFixed(this.precision));
          totalPercentage += percentage;
          if (totalPercentage > 100) {
            percentage -= totalPercentage - 100;
            // After adjusting the percentage, need to trim the numbers after decimal points again, otherwise it may not show
            // on chart due to very long number after decimal point.
            percentage = parseFloat(percentage.toFixed(this.precision));
          }
          text = percentage + '%';
          break;
      }
      ctx.save();
      ctx.beginPath();
      ctx.font = Chart.helpers.fontString(this.fontSize, this.fontStyle, this.fontFamily);
      var position, innerRadius, arcOffset;
      if (this.position === 'outside' || 
        this.position === 'border' && chartInstance.config.type === 'pie') {
        innerRadius = view.outerRadius / 2;
        var rangeFromCentre, offset = this.fontSize + 2,
          centreAngle = view.startAngle + ((view.endAngle - view.startAngle) / 2);
        if (this.position === 'border') {
          rangeFromCentre = (view.outerRadius - innerRadius) / 2 + innerRadius;
        } else if (this.position === 'outside') {
          rangeFromCentre = (view.outerRadius - innerRadius) + innerRadius + offset;
        }
        position = {
          x: view.x + (Math.cos(centreAngle) * rangeFromCentre),
          y: view.y + (Math.sin(centreAngle) * rangeFromCentre)
        };
        if (this.position === 'outside') {
          if (position.x < view.x) {
            position.x -= offset;
          } else {
            position.x += offset;
          }
          arcOffset = view.outerRadius + offset;
        }
      } else {
        innerRadius = view.innerRadius;
        position = element.tooltipPosition();
      }

      if (this.arc) {
        if (!arcOffset)
          arcOffset = (innerRadius + view.outerRadius) / 2;
        ctx.fillStyle = this.fontColor;
        ctx.textBaseline = 'middle';
        this.drawArcText(text, arcOffset, view);
      } else {
        var drawable, mertrics = ctx.measureText(text),
          left = position.x - mertrics.width / 2,
          right = position.x + mertrics.width / 2,
          top = position.y - this.fontSize / 2,
          bottom = position.y + this.fontSize / 2;
        if (this.position === 'outside') {
          drawable = this.checkTextBound(left, right, top, bottom);
        } else {
          drawable = element.inRange(left, top) && element.inRange(left, bottom) &&
            element.inRange(right, top) && element.inRange(right, bottom);
        }
        if (drawable) {
          ctx.fillStyle = this.fontColor;
          ctx.textBaseline = 'top';
          ctx.textAlign = 'center';
          ctx.fillText(text, position.x, position.y - this.fontSize / 2);
        }
      }
      ctx.restore();
    }
  };

  PieceLabel.prototype.parseOptions = function (chartInstance) {
    var pieceLabel = chartInstance.options.pieceLabel;
    if (pieceLabel) {
      this.chartInstance = chartInstance;
      this.ctx = chartInstance.chart.ctx;
      this.options = chartInstance.config.options;
      this.mode = pieceLabel.mode;
      this.position = pieceLabel.position || 'default';
      this.arc = pieceLabel.arc || false;
      this.format = pieceLabel.format;
      this.precision = pieceLabel.precision || 0;
      this.fontSize = pieceLabel.fontSize || this.options.defaultFontSize;
      this.fontColor = pieceLabel.fontColor || '#fff';
      this.fontStyle = pieceLabel.fontStyle || this.options.defaultFontStyle;
      this.fontFamily = pieceLabel.fontFamily || this.options.defaultFontFamily;
      this.hasTooltip = chartInstance.tooltip._active && chartInstance.tooltip._active.length;
      return true;
    } else {
      return false;
    }
  };

  PieceLabel.prototype.checkTextBound = function (left, right, top, bottom) {
    var labelBounds = this.labelBounds;
    for (var i = 0;i < labelBounds.length;++i) {
      var bound = labelBounds[i];
      var potins = [
        [left, top],
        [left, bottom],
        [right, top],
        [right, bottom]
      ];
      for (var j = 0;j < potins.length;++j) {
        var x = potins[j][0];
        var y = potins[j][1];
        if (x >= bound.left && x <= bound.right && y >= bound.top && y <= bound.bottom) {
          return false;
        }
      }
    }
    labelBounds.push({
      left: left,
      right: right,
      top: top,
      bottom: bottom
    });
    return true;
  };

  PieceLabel.prototype.drawArcText = function (str, radius, view) {
    var ctx = this.ctx,
      centerX = view.x,
      centerY = view.y,
      startAngle = view.startAngle,
      endAngle = view.endAngle;

    ctx.save();
    ctx.translate(centerX, centerY);
    var angleSize = endAngle - startAngle;
    startAngle += Math.PI / 2;
    endAngle += Math.PI / 2;
    var mertrics = ctx.measureText(str);
    startAngle += (endAngle - (mertrics.width / radius + startAngle)) / 2;
    if (endAngle - startAngle > angleSize) {
      ctx.restore();
      return;
    }
    ctx.rotate(startAngle);
    for (var i = 0; i < str.length; i++) {
      var char = str.charAt(i);
      mertrics = ctx.measureText(char);
      ctx.save();
      ctx.translate(0, -1 * radius);
      ctx.fillText(char, 0, 0);
      ctx.restore();
      ctx.rotate(mertrics.width / radius);
    }
    ctx.restore();
  };

  Chart.pluginService.register({
    beforeInit: function(chartInstance) {
      chartInstance.pieceLabel = new PieceLabel();
    },
    beforeDatasetsUpdate: function (chartInstance) {
      chartInstance.pieceLabel.beforeDatasetsUpdate(chartInstance);
    },
    afterDraw: function (chartInstance) {
      chartInstance.pieceLabel.afterDraw(chartInstance);
    }
  });
})();