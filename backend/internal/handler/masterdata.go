package handler

import (
	"github.com/gin-gonic/gin"

	"packsubmit/internal/dto"
	"packsubmit/internal/pkg/resp"
)

func (h *Handler) Customers(c *gin.Context) {
	items, err := h.MasterData.Customers(c.Query("keyword"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, items)
}

func (h *Handler) Suppliers(c *gin.Context) {
	items, err := h.MasterData.Suppliers(c.Query("keyword"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, items)
}

func (h *Handler) MySupplier(c *gin.Context) {
	user := currentUser(c)

	if user.SupplierID == nil {
		resp.OK(c, nil)
		return
	}

	supplier, err := h.MasterData.SupplierByID(*user.SupplierID)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, supplier)
}

func (h *Handler) Parts(c *gin.Context) {
	items, err := h.MasterData.Parts(currentUser(c), c.Query("supplier_id"), c.Query("keyword"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, items)
}

func (h *Handler) DictOptions(c *gin.Context) {
	items, err := h.MasterData.DictOptions(c.Query("type"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, items)
}

func (h *Handler) ContactList(c *gin.Context) {
	items, err := h.Contacts.List(currentUser(c), c.Query("supplier_id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, items)
}

func (h *Handler) ContactSave(c *gin.Context) {
	var input dto.ContactInput
	if !bindJSON(c, &input) {
		return
	}

	contact, err := h.Contacts.Create(currentUser(c), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, contact)
}

func (h *Handler) ContactUpdate(c *gin.Context) {
	var input dto.ContactInput
	if !bindJSON(c, &input) {
		return
	}

	contact, err := h.Contacts.Update(currentUser(c), c.Param("id"), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, contact)
}

func (h *Handler) ContactDelete(c *gin.Context) {
	if err := h.Contacts.Delete(currentUser(c), c.Param("id")); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "删除成功")
}
