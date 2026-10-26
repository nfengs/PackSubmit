<template>
  <div v-loading="loading" class="page-card">
    <div class="page-header">
      <h3 class="page-title">{{ isEdit ? '编辑包装确认单' : '新增包装确认单' }}</h3>
      <el-button v-if="isEdit" type="primary" plain @click="openCopyFrom">复制其他方案</el-button>
    </div>

    <el-form
      v-if="!loading"
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="140px"
      class="proposal-form"
    >
      <h4 class="form-section">基本信息</h4>
      <el-row :gutter="20">
        <el-col :xs="24" :md="12">
          <el-form-item label="填写日期" prop="write_date">
            <el-date-picker
              v-model="form.write_date"
              type="date"
              value-format="YYYY-MM-DD"
              placeholder="请选择填写日期"
              disabled
              style="width: 100%"
            />
            <div class="form-tip">填写日期固定为当天，不可修改</div>
          </el-form-item>
        </el-col>
      </el-row>

      <h4 class="form-section">客户资料</h4>
      <el-row :gutter="20">
        <el-col :xs="24" :md="12">
          <el-form-item label="客户名称" prop="customer">
            <el-select
              v-model="form.customer"
              placeholder="请选择客户（自动带出资料）"
              filterable
              style="width: 100%"
              @change="applyCustomer"
            >
              <el-option
                v-for="item in customers"
                :key="item.id"
                :label="item.name"
                :value="item.name"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="客户地址" prop="customer_address">
            <el-input v-model="form.customer_address" maxlength="255" placeholder="请输入客户地址" />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="客户联系人" prop="customer_contact">
            <el-input v-model="form.customer_contact" maxlength="50" placeholder="请输入客户联系人" />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="客户电话" prop="customer_phone">
            <el-input v-model="form.customer_phone" maxlength="30" placeholder="请输入客户电话" />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="客户邮箱" prop="customer_email">
            <el-input v-model="form.customer_email" maxlength="100" placeholder="请输入客户邮箱" />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="客户传真" prop="customer_fax">
            <el-input v-model="form.customer_fax" maxlength="30" placeholder="请输入客户传真" />
          </el-form-item>
        </el-col>
      </el-row>

      <h4 class="form-section">供应商资料</h4>
      <el-row :gutter="20">
        <el-col :xs="24" :md="12">
          <el-form-item label="供应商名称" prop="supplier">
            <el-select
              v-model="form.supplier"
              :placeholder="supplierLocked ? '已锁定为当前账号所属供应商' : '请选择供应商（自动带出资料）'"
              :disabled="supplierLocked"
              filterable
              :clearable="!supplierLocked"
              style="width: 100%"
              @change="applySupplier"
            >
              <el-option
                v-for="item in suppliers"
                :key="item.id"
                :label="item.name"
                :value="item.name"
              />
            </el-select>
            <div v-if="supplierLocked" class="form-tip">
              供应商只能使用自己所属供应商，供应商名称与代码不可修改
            </div>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="供应商代码" prop="supplier_code">
            <el-input
              v-model="form.supplier_code"
              disabled
              maxlength="50"
              placeholder="选择供应商后自动带出"
            />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="供应商地址" prop="supplier_address">
            <el-input
              v-model="form.supplier_address"
              maxlength="255"
              placeholder="选择供应商后自动带出，可修改"
            />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="供应商联系人" prop="contact">
            <el-select
              v-model="selectedContactId"
              placeholder="请选择联系人"
              filterable
              clearable
              :loading="contactsLoading"
              :no-data-text="contactsLoading ? '联系人加载中…' : '该供应商暂无联系人'"
              style="width: 100%"
              @change="applyContact"
            >
              <el-option
                v-for="item in supplierContacts"
                :key="item.id"
                :label="item.name"
                :value="item.id"
              >
                <span>{{ item.name }}</span>
                <span class="part-option-desc">{{ item.phone || '-' }} · {{ item.email || '-' }}</span>
              </el-option>
            </el-select>
            <div class="form-tip">
              联系人、电话、邮箱、传真由供应商在「联系人维护」中自行维护，选择后自动带出
            </div>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="供应商电话" prop="phone">
            <el-input
              v-model="form.phone"
              maxlength="30"
              placeholder="选择供应商后自动带出，可修改"
            />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="供应商邮箱" prop="supplier_email">
            <el-input
              v-model="form.supplier_email"
              maxlength="100"
              placeholder="选择供应商后自动带出，可修改"
            />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="供应商传真" prop="supplier_fax">
            <el-input
              v-model="form.supplier_fax"
              maxlength="30"
              placeholder="选择供应商后自动带出，可修改"
            />
          </el-form-item>
        </el-col>
      </el-row>

      <h4 class="form-section">产品信息</h4>
      <el-row :gutter="20">
        <el-col :xs="24" :md="12">
          <el-form-item label="项目车型" prop="project_model">
            <el-input
              v-model="form.project_model"
              :disabled="supplierLocked"
              maxlength="100"
              placeholder="选择零件号后自动带出"
            />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="零件名称" prop="part_name">
            <el-input
              v-model="form.part_name"
              :disabled="supplierLocked"
              maxlength="200"
              placeholder="选择零件号后自动带出"
            />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="零件号" prop="part_no">
            <el-select
              v-model="form.part_no"
              placeholder="输入零件号 / 零件名称 / 项目车型搜索"
              filterable
              :filter-method="filterPartOptions"
              :reserve-keyword="false"
              :loading="partsLoading"
              :no-data-text="partsLoading ? '零件号加载中…' : '未匹配到零件号，请换个关键词'"
              :disabled="isEdit && supplierLocked"
              style="width: 100%"
              @change="applyPart"
              @visible-change="handlePartVisibleChange"
            >
              <el-option
                v-for="item in partOptions"
                :key="item.id"
                :label="item.part_no"
                :value="item.part_no"
              >
                <span>{{ item.part_no }}</span>
                <span class="part-option-desc">{{ item.part_name }} · {{ item.project_name }}</span>
              </el-option>
            </el-select>
            <div v-if="supplierLocked && !partsLoading && parts.length === 0" class="form-tip">
              当前供应商暂无指定零件号，请联系管理员导入
            </div>
            <div v-else-if="parts.length > 0" class="form-tip">
              支持按零件号、零件名称、项目车型模糊搜索，只能从清单中选择
            </div>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="零件材料" prop="part_material">
            <el-input v-model="form.part_material" maxlength="100" placeholder="请输入零件材料" />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="产品净重(kg)" prop="part_weight">
            <el-input-number
              v-model="form.part_weight"
              :min="0"
              :precision="3"
              :step="0.1"
              :controls="false"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="产品外形尺寸（mm）" prop="part_size_l">
            <div class="size-group">
              <el-input-number v-model="form.part_size_l" :min="0" :step="1" :controls="false" />
              <span class="size-sep">×</span>
              <el-input-number v-model="form.part_size_w" :min="0" :step="1" :controls="false" />
              <span class="size-sep">×</span>
              <el-input-number v-model="form.part_size_h" :min="0" :step="1" :controls="false" />
            </div>
          </el-form-item>
        </el-col>
      </el-row>

      <h4 class="form-section">包装信息</h4>
      <el-row :gutter="20">
        <el-col :xs="24" :md="12">
          <el-form-item label="包装箱编号" prop="pack_box_no">
            <el-input v-model="form.pack_box_no" maxlength="50" placeholder="请输入包装箱编号" />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="包装箱材料" prop="pack_material">
            <el-radio-group v-model="form.pack_material">
              <el-radio
                v-for="item in packMaterials"
                :key="item.value"
                :value="item.value"
              >
                {{ item.label }}
              </el-radio>
            </el-radio-group>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="存放数量(件/箱)" prop="packing_qty">
            <el-input-number v-model="form.packing_qty" :min="0" :max="9999999" :controls="false" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="包装箱外尺寸（mm）" prop="box_size_l">
            <div class="size-group">
              <el-input-number v-model="form.box_size_l" :min="0" :step="1" :controls="false" />
              <span class="size-sep">×</span>
              <el-input-number v-model="form.box_size_w" :min="0" :step="1" :controls="false" />
              <span class="size-sep">×</span>
              <el-input-number v-model="form.box_size_h" :min="0" :step="1" :controls="false" />
            </div>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="毛重(kg)" prop="gross_weight">
            <el-input-number
              v-model="form.gross_weight"
              :min="0"
              :precision="3"
              :step="0.1"
              :controls="false"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="包装箱是否返回" prop="returnable">
            <el-radio-group v-model="form.returnable">
              <el-radio :value="1">是</el-radio>
              <el-radio :value="0">否</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="内衬是否返回" prop="dunnage_returnable">
            <el-radio-group v-model="form.dunnage_returnable">
              <el-radio :value="1">是</el-radio>
              <el-radio :value="0">否</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-col>
      </el-row>

      <h4 class="form-section">托盘信息（仅带托盘交付时填写）</h4>
      <el-form-item label="是否带托盘交付" prop="with_pallet">
        <el-radio-group v-model="form.with_pallet" @change="handleWithPalletChange">
          <el-radio :value="1">是</el-radio>
          <el-radio :value="0">否</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-row v-if="Number(form.with_pallet) === 1" :gutter="20">
        <el-col :xs="24" :md="12">
          <el-form-item label="满托盘外尺寸（mm）" prop="pallet_size_l">
            <div class="size-group">
              <el-input-number v-model="form.pallet_size_l" :min="0" :step="1" :controls="false" />
              <span class="size-sep">×</span>
              <el-input-number v-model="form.pallet_size_w" :min="0" :step="1" :controls="false" />
              <span class="size-sep">×</span>
              <el-input-number v-model="form.pallet_size_h" :min="0" :step="1" :controls="false" />
            </div>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="每层包装箱数量" prop="pallet_layer_qty">
            <el-input-number v-model="form.pallet_layer_qty" :min="0" :max="999999" :controls="false" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="堆码层数" prop="pallet_layer_count">
            <el-input-number v-model="form.pallet_layer_count" :min="0" :max="9999" :controls="false" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="每托盘零件数量" prop="pallet_parts_qty">
            <el-input-number
              v-model="form.pallet_parts_qty"
              :min="0"
              :max="99999999"
              disabled
              :controls="false"
              style="width: 100%"
            />
            <div class="form-tip">自动计算：每层包装箱数量 × 堆码层数 × 存放数量(件/箱)</div>
          </el-form-item>
        </el-col>
      </el-row>

      <h4 class="form-section">包装操作说明</h4>
      <el-form-item label="操作说明（选填）" prop="packaging_instruction">
        <el-input
          v-model="form.packaging_instruction"
          type="textarea"
          :rows="4"
          maxlength="5000"
          show-word-limit
          placeholder="请填写包装操作步骤、注意事项等"
        />
      </el-form-item>

      <h4 class="form-section">包装图片</h4>
      <el-row :gutter="20">
        <el-col :xs="24" :md="12">
          <el-form-item label="零件图片" required>
            <FileUpload
              v-model="attachmentGroups.part"
              :proposal-id="proposalId"
              :attach-type="ATTACH_TYPE.PART"
            />
            <div v-if="attachErrorShown && !attachmentGroups.part.length" class="attach-error">
              请上传零件图片
            </div>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="内部排列" required>
            <FileUpload
              v-model="attachmentGroups.inner"
              :proposal-id="proposalId"
              :attach-type="ATTACH_TYPE.INNER"
            />
            <div v-if="attachErrorShown && !attachmentGroups.inner.length" class="attach-error">
              请上传内部排列图片
            </div>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="整体包装" required>
            <FileUpload
              v-model="attachmentGroups.final"
              :proposal-id="proposalId"
              :attach-type="ATTACH_TYPE.FINAL"
            />
            <div v-if="attachErrorShown && !attachmentGroups.final.length" class="attach-error">
              请上传整体包装图片
            </div>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-form-item label="包装图片" required>
            <FileUpload
              v-model="attachmentGroups.packaging"
              :proposal-id="proposalId"
              :attach-type="ATTACH_TYPE.PACKAGING"
            />
            <div v-if="attachErrorShown && !attachmentGroups.packaging.length" class="attach-error">
              请上传包装图片
            </div>
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item>
        <el-button type="primary" :loading="saving" @click="handleSave(false)">保存草稿</el-button>
        <el-button type="success" :loading="submitting" @click="handleSave(true)">
          保存并提交
        </el-button>
        <el-button @click="router.back()">返回</el-button>
      </el-form-item>
    </el-form>

    <el-dialog v-model="copyFromVisible" title="复制其他方案信息" width="560px">
      <el-alert
        type="info"
        :closable="false"
        show-icon
        title="将所选方案的表单信息填入当前编辑页面；零件号、零件名称、项目车型与供应商资料不会被覆盖"
        style="margin-bottom: 16px"
      />
      <el-select
        v-model="copyFromId"
        placeholder="输入方案编号 / 零件名称搜索方案"
        filterable
        remote
        clearable
        :remote-method="searchCopyFrom"
        :loading="copyFromLoading"
        style="width: 100%"
      >
        <el-option
          v-for="item in copyFromOptions"
          :key="item.id"
          :label="`${item.proposal_no} · ${item.part_name || '未填写零件名称'}（V${item.version}）`"
          :value="item.id"
        />
      </el-select>
      <template #footer>
        <el-button @click="copyFromVisible = false">取消</el-button>
        <el-button type="primary" :loading="copyFromApplying" :disabled="!copyFromId" @click="handleCopyFrom">
          填入当前表单
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import FileUpload from '@/components/FileUpload.vue';
import { createProposal, getProposalDetail, getProposalList, updateProposal, submitProposal } from '@/api/proposal';
import { bindAttachments } from '@/api/attachment';
import {
  getCustomers,
  getMySupplier,
  getSupplierParts,
  getSuppliers,
} from '@/api/masterData';
import { getSupplierContacts } from '@/api/supplierContact';
import { useUserStore } from '@/store/user';
import { ATTACH_TYPE, PACK_MATERIAL_OPTIONS } from '@/utils/constants';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const isEdit = computed(() => route.name === 'ProposalEdit');
const proposalId = computed(() => (isEdit.value ? Number(route.params.id) : 0));
/** 供应商只能使用自己所属供应商 */
const supplierLocked = computed(() => userStore.isSubmitter);

const formRef = ref(null);
const loading = ref(false);
const saving = ref(false);
const submitting = ref(false);

const customers = ref([]);
const suppliers = ref([]);
const parts = ref([]);
const partsLoading = ref(false);
const packMaterials = PACK_MATERIAL_OPTIONS;

/** 供应商联系人（由供应商自行维护） */
const supplierContacts = ref([]);
const contactsLoading = ref(false);
const selectedContactId = ref('');

/** 复制其他方案 */
const copyFromVisible = ref(false);
const copyFromLoading = ref(false);
const copyFromApplying = ref(false);
const copyFromOptions = ref([]);
const copyFromId = ref(null);

/** 零件号搜索关键词 */
const partKeyword = ref('');

/** 按零件号 / 零件名称 / 项目车型过滤零件清单 */
const filteredParts = computed(() => {
  const keyword = partKeyword.value.trim().toLowerCase();
  if (!keyword) return parts.value;

  return parts.value.filter((item) =>
    [item.part_no, item.part_name, item.project_name].some((field) =>
      String(field || '')
        .toLowerCase()
        .includes(keyword),
    ),
  );
});

/** 下拉选项：历史单据的零件号不在清单中时保留展示，避免下拉框显示为空 */
const partOptions = computed(() => {
  const current = String(form.part_no || '').trim();
  const list = filteredParts.value;

  if (!current || partKeyword.value.trim()) return list;
  if (parts.value.some((item) => item.part_no === current)) return list;

  return [
    {
      id: `legacy-${current}`,
      part_no: current,
      part_name: form.part_name,
      project_name: form.project_model,
    },
    ...list,
  ];
});

/**
 * 自定义搜索：匹配零件号、零件名称、项目车型
 * @param {string} keyword 输入关键词
 */
function filterPartOptions(keyword) {
  partKeyword.value = String(keyword || '').trim();
}

/**
 * 关闭下拉时清空关键词，下次打开显示完整清单
 * @param {boolean} visible 下拉是否展开
 */
function handlePartVisibleChange(visible) {
  if (!visible) {
    partKeyword.value = '';
  }
}

/** 按附件类型分组的列表 */
const attachmentGroups = reactive({
  [ATTACH_TYPE.PART]: [],
  [ATTACH_TYPE.INNER]: [],
  [ATTACH_TYPE.FINAL]: [],
  [ATTACH_TYPE.PACKAGING]: [],
});

/** 提交校验失败后展示图片缺失提示 */
const attachErrorShown = ref(false);

/** 图片类型与名称（提交时每一类都至少要上传一张） */
const ATTACH_LABELS = [
  [ATTACH_TYPE.PART, '零件图片'],
  [ATTACH_TYPE.INNER, '内部排列'],
  [ATTACH_TYPE.FINAL, '整体包装'],
  [ATTACH_TYPE.PACKAGING, '包装图片'],
];

/** 数值型字段，提交前统一把空值转成 0 */
const NUMBER_FIELDS = [
  'quantity',
  'part_weight', 'part_size_l', 'part_size_w', 'part_size_h',
  'box_size_l', 'box_size_w', 'box_size_h', 'gross_weight',
  'packing_qty',
  'pallet_size_l', 'pallet_size_w', 'pallet_size_h',
  'pallet_layer_qty', 'pallet_layer_count', 'pallet_parts_qty',
];

/** 是/否单选项：默认不选，提交前仍为空则保持 null */
const CHOICE_FIELDS = ['returnable', 'dunnage_returnable', 'with_pallet'];

/** 托盘信息字段（不带托盘交付时统一清零） */
const PALLET_FIELDS = [
  'pallet_size_l', 'pallet_size_w', 'pallet_size_h',
  'pallet_layer_qty', 'pallet_layer_count', 'pallet_parts_qty',
];

const form = reactive({
  customer: '',
  spec: '',
  quantity: 0,
  supplier: '',
  contact: '',
  phone: '',
  description: '',

  write_date: todayString(),
  project_model: '',
  part_name: '',
  part_no: '',
  part_material: '',
  customer_address: '',
  customer_contact: '',
  customer_phone: '',
  customer_email: '',
  customer_fax: '',
  supplier_code: '',
  supplier_address: '',
  supplier_email: '',
  supplier_fax: '',
  pack_box_no: '',

  pack_material: '',
  part_weight: 0,
  part_size_l: 0,
  part_size_w: 0,
  part_size_h: 0,
  box_size_l: 0,
  box_size_w: 0,
  box_size_h: 0,
  gross_weight: 0,
  // 是/否单选项默认不选
  returnable: null,
  dunnage_returnable: null,
  packing_qty: 0,
  with_pallet: null,
  pallet_size_l: 0,
  pallet_size_w: 0,
  pallet_size_h: 0,
  pallet_layer_qty: 0,
  pallet_layer_count: 0,
  pallet_parts_qty: 0,
  packaging_instruction: '',
});

/**
 * 文本 / 下拉必填规则
 * @param {string} label 字段中文名
 * @param {'blur'|'change'} trigger 校验时机
 */
function requiredRule(label, trigger = 'blur') {
  return [{ required: true, whitespace: true, message: `请填写${label}`, trigger }];
}

/**
 * 单选必填规则（取值是数字 1/0，不能用 required + whitespace，0 会被判为空）
 * @param {string} label 字段中文名
 * @param {'blur'|'change'} trigger 校验时机
 */
function choiceRule(label, trigger = 'change') {
  return {
    validator: (_rule, value, callback) => {
      if (value === null || value === undefined || value === '') {
        return callback(new Error(`请选择${label}`));
      }

      return callback();
    },
    trigger,
  };
}

/** 带托盘交付时，托盘相关字段才需要填写 */
const isPalletRequired = () => Number(form.with_pallet) === 1;

/**
 * 数值必填：未填写时值为 0，要求大于 0
 * @param {string} label 字段中文名
 * @param {() => boolean} [enabled] 是否参与校验
 */
function numberRule(label, enabled = () => true) {
  return {
    validator: (_rule, value, callback) => {
      if (!enabled()) return callback();
      if (Number(value) > 0) return callback();

      return callback(new Error(`请填写${label}`));
    },
    trigger: ['blur', 'change'],
  };
}

/**
 * 毛重校验：必须大于 0，且大于「单件净重 × 存放件数」
 */
function grossWeightRule() {
  return {
    validator: (_rule, value, callback) => {
      const gross = Number(value);

      if (gross <= 0) {
        return callback(new Error('请填写毛重'));
      }

      const minGross = Number(form.part_weight) * Number(form.packing_qty);

      if (minGross > 0 && gross <= minGross) {
        return callback(new Error(`毛重需大于 单件净重 × 存放件数（即大于 ${trimNumber(minGross)} kg）`));
      }

      return callback();
    },
    trigger: ['blur', 'change'],
  };
}

/**
 * 尺寸三元组校验：长宽高都要大于 0，且必须是整数（单位 mm）
 * @param {string[]} fields 尺寸字段名
 * @param {string} label 字段中文名
 * @param {() => boolean} [enabled] 是否参与校验
 */
function sizeRule(fields, label, enabled = () => true) {
  return {
    validator: (_rule, _value, callback) => {
      if (!enabled()) return callback();
      if (!fields.every((key) => Number(form[key]) > 0)) {
        return callback(new Error(`请填写完整的${label}（长×宽×高）`));
      }

      if (!fields.every((key) => Number.isInteger(Number(form[key])))) {
        return callback(new Error(`${label}不能带小数，请填写整数`));
      }

      return callback();
    },
    trigger: ['blur', 'change'],
  };
}

/** 除“操作说明”外全部必填 */
const rules = {
  write_date: requiredRule('填写日期', 'change'),
  customer: requiredRule('客户名称', 'change'),
  customer_address: requiredRule('客户地址'),
  customer_contact: requiredRule('客户联系人'),
  customer_phone: requiredRule('客户电话'),
  customer_email: [
    ...requiredRule('客户邮箱'),
    { type: 'email', message: '客户邮箱格式不正确', trigger: 'blur' },
  ],
  customer_fax: requiredRule('客户传真'),

  supplier: requiredRule('供应商名称', 'change'),
  supplier_code: requiredRule('供应商代码'),
  supplier_address: requiredRule('供应商地址'),
  contact: requiredRule('供应商联系人'),
  phone: requiredRule('供应商电话'),
  supplier_email: [
    ...requiredRule('供应商邮箱'),
    { type: 'email', message: '供应商邮箱格式不正确', trigger: 'blur' },
  ],
  supplier_fax: requiredRule('供应商传真'),

  part_no: requiredRule('零件号', 'change'),
  part_name: requiredRule('零件名称'),
  project_model: requiredRule('项目车型'),
  part_material: requiredRule('零件材料'),
  part_weight: [numberRule('产品净重')],
  part_size_l: [sizeRule(['part_size_l', 'part_size_w', 'part_size_h'], '产品外形尺寸（mm）')],

  pack_box_no: requiredRule('包装箱编号'),
  pack_material: requiredRule('包装箱材料', 'change'),
  packing_qty: [numberRule('存放数量')],
  box_size_l: [sizeRule(['box_size_l', 'box_size_w', 'box_size_h'], '包装箱外尺寸（mm）')],
  gross_weight: [grossWeightRule()],
  returnable: [choiceRule('包装箱是否返回')],
  dunnage_returnable: [choiceRule('内衬是否返回')],

  with_pallet: [choiceRule('是否带托盘交付')],
  pallet_size_l: [
    sizeRule(['pallet_size_l', 'pallet_size_w', 'pallet_size_h'], '满托盘外尺寸（mm）', isPalletRequired),
  ],
  pallet_layer_qty: [numberRule('每层包装箱数量', isPalletRequired)],
  pallet_layer_count: [numberRule('堆码层数', isPalletRequired)],
  pallet_parts_qty: [numberRule('每托盘零件数量', isPalletRequired)],
};

/**
 * 加载客户 / 供应商档案（选择后自动带出资料）
 */
async function loadMasterData() {
  const [customerList, supplierList] = await Promise.all([getCustomers(), getSuppliers()]);
  customers.value = customerList || [];
  suppliers.value = supplierList || [];
}

/**
 * 选择客户后，从档案中回填客户资料
 * @param {string} name 客户名称
 */
function applyCustomer(name) {
  const target = customers.value.find((item) => item.name === name);
  if (!target) return;

  form.customer_address = target.address || '';
  form.customer_contact = target.contact || '';
  form.customer_phone = target.phone || '';
  form.customer_email = target.email || '';
  form.customer_fax = target.fax || '';
}

/**
 * 加载供应商联系人（供应商自行维护，可增删改）
 * @param {string} supplierName 供应商名称
 */
async function loadContacts(supplierName) {
  supplierContacts.value = [];
  selectedContactId.value = '';

  if (!supplierName) return;

  contactsLoading.value = true;

  try {
    const data = await getSupplierContacts({ supplier: supplierName });
    supplierContacts.value = data.list || [];
  } finally {
    contactsLoading.value = false;
  }
}

/**
 * 选择一个联系人后，带出电话 / 邮箱 / 传真
 * @param {number|string} id 联系人ID
 */
function applyContact(id) {
  const target = supplierContacts.value.find((item) => item.id === id);

  if (!target) {
    form.contact = '';
    form.phone = '';
    form.supplier_email = '';
    form.supplier_fax = '';
    return;
  }

  form.contact = target.name || '';
  form.phone = target.phone || '';
  form.supplier_email = target.email || '';
  form.supplier_fax = target.fax || '';
}

/**
 * 编辑回显：按联系人姓名 + 电话匹配已选联系人
 */
function syncSelectedContact() {
  const matched = supplierContacts.value.find(
    (item) => item.name === form.contact && item.phone === form.phone,
  ) || supplierContacts.value.find((item) => item.name === form.contact);

  selectedContactId.value = matched ? matched.id : '';
}

/**
 * 复制其他方案时不可覆盖的字段：零件身份 + 供应商资料
 */
const COPY_PROTECTED_FIELDS = [
  'part_no',
  'part_name',
  'project_model',
  'supplier',
  'supplier_code',
  'supplier_address',
  'contact',
  'phone',
  'supplier_email',
  'supplier_fax',
];

/**
 * 搜索可复制的方案
 * @param {string} keyword
 */
async function searchCopyFrom(keyword = '') {
  copyFromLoading.value = true;

  try {
    const data = await getProposalList({ keyword, page: 1, page_size: 20 });
    copyFromOptions.value = (data.list || []).filter(
      (item) => item.id !== proposalId.value,
    );
  } finally {
    copyFromLoading.value = false;
  }
}

function openCopyFrom() {
  copyFromId.value = null;
  copyFromOptions.value = [];
  copyFromVisible.value = true;
  searchCopyFrom('');
}

/**
 * 将所选方案的表单信息填入当前表单（保留零件号 / 零件名称 / 项目车型与供应商资料）
 */
async function handleCopyFrom() {
  if (!copyFromId.value) return;

  copyFromApplying.value = true;

  try {
    const source = await getProposalDetail(copyFromId.value);

    Object.keys(form).forEach((key) => {
      if (key === 'write_date' || COPY_PROTECTED_FIELDS.includes(key)) return;
      if (source[key] === undefined || source[key] === null) return;
      form[key] = source[key];
    });

    // 数值字段转 number
    NUMBER_FIELDS.forEach((key) => {
      if (COPY_PROTECTED_FIELDS.includes(key)) return;
      form[key] = Number(form[key] || 0);
    });

    // 是/否字段统一为 null / 1 / 0
    CHOICE_FIELDS.forEach((key) => {
      const value = form[key];
      form[key] = value === null || value === undefined || value === '' ? null : Number(value);
    });

    // 派生字段重算：数量 = 存放件数；规格尺寸 = 包装箱外尺寸
    form.quantity = Number(form.packing_qty) || 0;
    const length = Number(form.box_size_l);
    const width = Number(form.box_size_w);
    const height = Number(form.box_size_h);
    form.spec = length > 0 && width > 0 && height > 0
      ? `${length}*${width}*${height}mm`
      : '';
    syncPalletPartsQty();

    formRef.value?.clearValidate();
    copyFromVisible.value = false;
    ElMessage.success('已复制方案信息，零件号与零件名称保持不变');
  } finally {
    copyFromApplying.value = false;
  }
}

/**
 * 用供应商档案回填供应商资料，并优先带出默认联系人
 * @param {{name?:string,code?:string,address?:string,contact?:string,phone?:string,email?:string,fax?:string}} target
 */
async function fillSupplier(target = {}) {
  form.supplier = target.name || '';
  form.supplier_code = target.code || '';
  form.supplier_address = target.address || '';

  await loadContacts(form.supplier);

  const preferred = supplierContacts.value.find((item) => Number(item.is_default) === 1)
    || supplierContacts.value[0];

  if (preferred) {
    selectedContactId.value = preferred.id;
    applyContact(preferred.id);
    return;
  }

  // 供应商尚未维护联系人时，清空联系人资料（联系人统一在「联系人维护」中维护）
  form.contact = '';
  form.phone = '';
  form.supplier_email = '';
  form.supplier_fax = '';
}

/**
 * 加载供应商指定零件号
 * @param {string} supplierName 供应商名称
 */
async function loadParts(supplierName) {
  if (!supplierName) {
    parts.value = [];
    return;
  }

  partsLoading.value = true;

  try {
    const data = await getSupplierParts(supplierName);
    parts.value = data || [];
  } finally {
    partsLoading.value = false;
  }
}

/**
 * 选择零件号后，带出零件名称与项目名称
 * @param {string} partNo 零件号
 */
function applyPart(partNo) {
  const target = parts.value.find((item) => item.part_no === partNo);
  if (!target) return;

  form.part_name = target.part_name || '';
  form.project_model = target.project_name || '';
}

/**
 * 选择供应商后，从档案中回填供应商资料，并加载该供应商的指定零件号
 * @param {string} name 供应商名称
 */
async function applySupplier(name) {
  await fillSupplier(suppliers.value.find((item) => item.name === name));

  form.part_no = '';
  form.part_name = '';
  form.project_model = '';

  await loadParts(name);
}

/**
 * 切换是否带托盘交付：不带托盘时清空托盘信息
 * @param {number} value
 */
function handleWithPalletChange(value) {
  if (Number(value) === 1) return;

  PALLET_FIELDS.forEach((key) => {
    form[key] = 0;
  });
}

/**
 * 每托盘零件数量 = 每层包装箱数量 × 堆码层数 × 存放数量(件/箱)
 */
function syncPalletPartsQty() {
  form.pallet_parts_qty =
    (Number(form.pallet_layer_qty) || 0)
    * (Number(form.pallet_layer_count) || 0)
    * (Number(form.packing_qty) || 0);
}

// 三个来源字段变化时自动重算，不允许手工填写
watch(
  () => [form.with_pallet, form.pallet_layer_qty, form.pallet_layer_count, form.packing_qty],
  () => {
    if (Number(form.with_pallet) !== 1) return;

    syncPalletPartsQty();
  },
);

// 单件净重 / 存放件数变化时，联动复检毛重
watch(
  () => [form.part_weight, form.packing_qty],
  () => {
    formRef.value?.validateField('gross_weight').catch(() => {});
  },
);

/**
 * 供应商：锁定为当前账号所属供应商并回填资料
 */
async function applyMySupplier() {
  const mine = await getMySupplier();
  if (mine?.name) {
    await fillSupplier(mine);
  }
}

/**
 * 编辑模式加载详情
 */
async function loadDetail() {
  const data = await getProposalDetail(proposalId.value);

  if (!data.permissions?.can_edit) {
    ElMessage.warning('当前状态不允许编辑，已跳转到详情页');
    router.replace(`/proposal/detail/${proposalId.value}`);
    return;
  }

  Object.keys(form).forEach((key) => {
    if (data[key] !== undefined && data[key] !== null) {
      form[key] = data[key];
    }
  });

  // 数值字段统一转为 number
  NUMBER_FIELDS.forEach((key) => {
    form[key] = Number(form[key] || 0);
  });
  form.quantity = Number(form.quantity || 0);

  // 是/否字段保持“未选择”为空，避免默认显示为“否”
  CHOICE_FIELDS.forEach((key) => {
    const value = data[key];
    form[key] = value === null || value === undefined || value === '' ? null : Number(value);
  });

  // 填写日期固定为当天，编辑时也重置为当天
  form.write_date = todayString();

  // 附件按类型分组
  Object.keys(attachmentGroups).forEach((type) => {
    attachmentGroups[type] = (data.attachments || []).filter(
      (item) => (item.attach_type || ATTACH_TYPE.ATTACHMENT) === type,
    );
  });
}

/**
 * 今天（YYYY-MM-DD）
 */
function todayString() {
  const date = new Date();
  const pad = (value) => String(value).padStart(2, '0');

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * 数字去除多余的小数位 0（如 3.000 → 3、3.500 → 3.5）
 * @param {number} value
 */
function trimNumber(value) {
  return Number(value.toFixed(3)).toString();
}

/**
 * 由包装信息推导不在表单展示的字段（规格尺寸/数量）
 * @param {object} payload
 */
function fillDerivedFields(payload) {
  const length = Number(payload.box_size_l || 0);
  const width = Number(payload.box_size_w || 0);
  const height = Number(payload.box_size_h || 0);

  if (!payload.spec && length > 0 && width > 0 && height > 0) {
    payload.spec = `${length}*${width}*${height}mm`;
  }

  if (!Number(payload.quantity) && Number(payload.packing_qty)) {
    payload.quantity = Number(payload.packing_qty);
  }
}

/**
 * 组装提交数据
 */
function buildPayload() {
  const payload = { ...form };

  NUMBER_FIELDS.forEach((key) => {
    if (payload[key] === null || payload[key] === undefined || payload[key] === '') {
      payload[key] = 0;
    }
  });

  // 是/否未选择时保持为空，不默认成“否”
  CHOICE_FIELDS.forEach((key) => {
    const value = payload[key];
    payload[key] =
      value === null || value === undefined || value === '' ? null : Number(value) === 1 ? 1 : 0;
  });

  // 未选择或不带托盘交付时，托盘信息不提交
  if (payload.with_pallet !== 1) {
    PALLET_FIELDS.forEach((key) => {
      payload[key] = 0;
    });
  } else {
    // 每托盘零件数量由公式计算，避免手工值入库
    payload.pallet_parts_qty =
      (Number(payload.pallet_layer_qty) || 0)
      * (Number(payload.pallet_layer_count) || 0)
      * (Number(payload.packing_qty) || 0);
  }

  payload.write_date = payload.write_date || todayString();
  fillDerivedFields(payload);

  return payload;
}

/**
 * 全部附件（用于新增后绑定）
 */
function allAttachments() {
  return Object.values(attachmentGroups).flat();
}

/**
 * 未上传的图片类型
 * @return {string[]} 缺失的图片名称
 */
function missingAttachments() {
  return ATTACH_LABELS.filter(([type]) => !(attachmentGroups[type] || []).length).map(
    ([, label]) => label,
  );
}

/**
 * 提交前校验：除“操作说明”外全部必填，且四类包装图片都必须上传
 */
async function validateForSubmit() {
  const valid = await formRef.value.validate().catch(() => false);
  const missingImages = missingAttachments();

  attachErrorShown.value = missingImages.length > 0;

  if (valid && !missingImages.length) return true;

  const tips = [];
  if (!valid) tips.push('还有必填项未填写');
  if (missingImages.length) tips.push(`还有图片未上传：${missingImages.join('、')}`);

  ElMessage.warning(`${tips.join('；')}，请补全后再提交`);
  await scrollToFirstError();

  return false;
}

/**
 * 保存草稿：只校验客户与邮箱格式，允许其他字段未填完
 */
async function validateForDraft() {
  formRef.value.clearValidate();

  try {
    await formRef.value.validateField(['customer', 'customer_email', 'supplier_email']);
    return true;
  } catch {
    ElMessage.warning('请选择客户，并检查邮箱格式');
    return false;
  }
}

/**
 * 滚动到第一个校验失败的字段或缺失的图片
 */
async function scrollToFirstError() {
  await nextTick();

  const target = document.querySelector(
    '.proposal-form .el-form-item.is-error, .proposal-form .attach-error',
  );

  if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * 保存 / 保存并提交
 * @param {boolean} andSubmit
 */
async function handleSave(andSubmit) {
  const valid = andSubmit ? await validateForSubmit() : await validateForDraft();
  if (!valid) return;

  const isSubmitting = andSubmit;
  if (isSubmitting) submitting.value = true;
  else saving.value = true;

  try {
    if (isEdit.value) {
      await updateProposal(proposalId.value, buildPayload());

      if (andSubmit) {
        await submitProposal(proposalId.value);
      }

      ElMessage.success(andSubmit ? '保存并提交成功' : '保存成功');
      router.replace(`/proposal/detail/${proposalId.value}`);
      return;
    }

    const created = await createProposal(buildPayload());

    // 新增时先上传暂存附件，保存成功后绑定到方案
    const pendingIds = allAttachments()
      .filter((item) => !Number(item.proposal_id))
      .map((item) => item.id);

    if (pendingIds.length > 0) {
      await bindAttachments(created.id, pendingIds);
    }

    if (andSubmit) {
      await submitProposal(created.id);
    }

    ElMessage.success(andSubmit ? '方案已创建并提交评审' : '方案已保存为草稿');
    router.replace(`/proposal/detail/${created.id}`);
  } finally {
    saving.value = false;
    submitting.value = false;
  }
}

onMounted(async () => {
  loading.value = true;

  try {
    await loadMasterData();
    if (isEdit.value) {
      await loadDetail();
    }

    // 供应商新增时默认带出自己所属供应商；编辑时保留单据已保存的资料
    if (supplierLocked.value && !isEdit.value) {
      await applyMySupplier();
    }

    if (form.supplier) {
      // 编辑回显：加载该供应商联系人，并回选方案已保存的联系人
      if (isEdit.value) {
        await loadContacts(form.supplier);
        syncSelectedContact();
      }

      await loadParts(form.supplier);
    }
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.page-header .page-title {
  margin: 0;
}

.proposal-form {
  max-width: 1180px;
}

.form-section {
  margin: 18px 0 12px;
  padding-left: 8px;
  font-size: 15px;
  border-left: 3px solid var(--el-color-primary);
}

.size-group {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
}

.size-group :deep(.el-input-number) {
  flex: 1;
  min-width: 0;
}

.size-sep {
  color: #909399;
}

.attach-error {
  width: 100%;
  font-size: 12px;
  line-height: 1.5;
  color: var(--el-color-danger);
}

.form-tip {
  width: 100%;
  margin-top: 2px;
  font-size: 12px;
  line-height: 1.5;
  color: #909399;
}

.part-option-desc {
  float: right;
  margin-left: 16px;
  font-size: 12px;
  color: #909399;
}
</style>
